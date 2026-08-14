<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CustomerAccounts\Actions\CompleteGuestOrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Actions\OpenGuestOrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Contracts\VerifiesGuestOrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimAttemptOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimState;
use Liberu\Ecommerce\CustomerAccounts\Events\GuestOrderClaimGranted;
use Liberu\Ecommerce\CustomerAccounts\Events\GuestOrderClaimOpened;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\AttemptsAreAppendOnly;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ClaimNotClaimable;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ClaimRateLimited;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ClaimRefused;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ClaimVerificationUnavailable;
use Liberu\Ecommerce\CustomerAccounts\Models\ClaimAttempt;
use Liberu\Ecommerce\CustomerAccounts\Models\OrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Queries\IsEntitledToOrder;
use Liberu\Ecommerce\CustomerAccounts\Tests\Fakes\FakeClaimVerifier;

function verifier(array $orders = [['tenant-a', 'ORD-1001', 'buyer@example.test']], bool $available = true): void
{
    App::instance(VerifiesGuestOrderClaim::class, new FakeClaimVerifier($orders, $available));
}

it('opens a claim that entitles nothing until the proof is presented', function (): void {
    verifier();

    $proof = (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'buyer@example.test', 'person-1');
    $claim = OrderClaim::query()->where('reference', $proof->claimReference)->first();

    expect($claim->state)->toBe(ClaimState::Pending)
        ->and((new IsEntitledToOrder())('tenant-a', 'person-1', 'ORD-1001'))->toBeFalse();

    (new CompleteGuestOrderClaim())($proof->claimReference, $proof->token);

    expect($claim->refresh()->state)->toBe(ClaimState::Granted)
        ->and((new IsEntitledToOrder())('tenant-a', 'person-1', 'ORD-1001'))->toBeTrue();
});

it('stores neither the address nor the token in readable form', function (): void {
    verifier();

    $proof = (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'buyer@example.test', 'person-1');
    $claim = OrderClaim::query()->where('reference', $proof->claimReference)->first();

    expect($claim->email_fingerprint)->not->toBe('buyer@example.test')
        ->and($claim->email_fingerprint)->toHaveLength(64)
        ->and($claim->token_fingerprint)->not->toBe($proof->token);
});

it('clears the proof once it has been spent', function (): void {
    verifier();

    $proof = (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'buyer@example.test', 'person-1');
    $claim = (new CompleteGuestOrderClaim())($proof->claimReference, $proof->token);

    expect($claim->token_fingerprint)->toBeNull();

    expect(fn () => (new CompleteGuestOrderClaim())($proof->claimReference, $proof->token))
        ->toThrow(ClaimNotClaimable::class);
});

it('refuses a wrong reference and a wrong address identically', function (): void {
    verifier();

    $wrongReference = null;
    $wrongAddress = null;

    try {
        (new OpenGuestOrderClaim())('tenant-a', 'ORD-9999', 'buyer@example.test', 'person-1');
    } catch (ClaimRefused $e) {
        $wrongReference = $e;
    }

    try {
        (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'someone@example.test', 'person-1');
    } catch (ClaimRefused $e) {
        $wrongAddress = $e;
    }

    // Same class, same message. There is nothing for a surface to decode,
    // because the seam never told us which of the two it was.
    expect($wrongReference)->toBeInstanceOf(ClaimRefused::class)
        ->and($wrongAddress)->toBeInstanceOf(ClaimRefused::class)
        ->and($wrongReference->getMessage())->toBe($wrongAddress->getMessage());
});

it('creates no claim row for a refused attempt', function (): void {
    verifier();

    try {
        (new OpenGuestOrderClaim())('tenant-a', 'ORD-9999', 'buyer@example.test', 'person-1');
    } catch (ClaimRefused) {
        // expected
    }

    // A guess at a reference must not create a row keyed by it, or the table
    // becomes the oracle the endpoint was careful not to be.
    expect(OrderClaim::query()->count())->toBe(0)
        ->and(ClaimAttempt::query()->count())->toBe(1);
});

it('records every attempt, especially the failures', function (): void {
    verifier();

    (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'buyer@example.test', 'person-1');

    try {
        (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'wrong@example.test', 'person-2');
    } catch (ClaimRefused) {
        // expected
    }

    expect(ClaimAttempt::query()->pluck('outcome')->all())
        ->toBe([ClaimAttemptOutcome::Issued, ClaimAttemptOutcome::Refused]);
});

it('rate-limits by order reference, so one order cannot be hammered', function (): void {
    verifier();
    Config::set('customer-accounts.claim.max_attempts', 3);

    foreach (range(1, 3) as $i) {
        try {
            (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', "guess{$i}@example.test", 'person-1');
        } catch (ClaimRefused) {
            // expected
        }
    }

    expect(fn () => (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'guess4@example.test', 'person-1'))
        ->toThrow(ClaimRateLimited::class);
});

it('rate-limits by address, so one person cannot walk a list of references', function (): void {
    verifier();
    Config::set('customer-accounts.claim.max_attempts', 3);

    foreach (range(1, 3) as $i) {
        try {
            (new OpenGuestOrderClaim())('tenant-a', "ORD-200{$i}", 'walker@example.test', 'person-1');
        } catch (ClaimRefused) {
            // expected
        }
    }

    expect(fn () => (new OpenGuestOrderClaim())('tenant-a', 'ORD-2004', 'walker@example.test', 'person-1'))
        ->toThrow(ClaimRateLimited::class);
});

it('records the rate-limited attempt itself', function (): void {
    verifier();
    Config::set('customer-accounts.claim.max_attempts', 1);

    (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'buyer@example.test', 'person-1');

    try {
        (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'buyer@example.test', 'person-1');
    } catch (ClaimRateLimited) {
        // expected
    }

    expect(ClaimAttempt::query()->where('outcome', ClaimAttemptOutcome::RateLimited)->count())->toBe(1);
});

it('closes claiming when nothing verifies, rather than opening it', function (): void {
    // Nothing bound at all.
    expect(fn () => (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'buyer@example.test', 'person-1'))
        ->toThrow(ClaimVerificationUnavailable::class);

    expect(OrderClaim::query()->count())->toBe(0)
        ->and(ClaimAttempt::query()->where('outcome', ClaimAttemptOutcome::VerifierUnavailable)->count())->toBe(1);
});

it('treats a verifier that cannot answer as unavailable and not as a refusal', function (): void {
    verifier(available: false);

    expect(fn () => (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'buyer@example.test', 'person-1'))
        ->toThrow(ClaimVerificationUnavailable::class);
});

it('expires a claim whose proof arrived too late', function (): void {
    verifier();
    Config::set('customer-accounts.claim.token_ttl_minutes', 30);

    $proof = (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'buyer@example.test', 'person-1');

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addHours(2));

    expect(fn () => (new CompleteGuestOrderClaim())($proof->claimReference, $proof->token))
        ->toThrow(ClaimNotClaimable::class);

    expect(OrderClaim::query()->first()->state)->toBe(ClaimState::Expired);

    CarbonImmutable::setTestNow();
});

it('refuses a wrong proof and records the attempt', function (): void {
    verifier();

    $proof = (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'buyer@example.test', 'person-1');

    expect(fn () => (new CompleteGuestOrderClaim())($proof->claimReference, 'not-the-token'))
        ->toThrow(ClaimRefused::class);

    expect(ClaimAttempt::query()->where('outcome', ClaimAttemptOutcome::TokenMismatch)->count())->toBe(1)
        ->and(OrderClaim::query()->first()->state)->toBe(ClaimState::Pending);
});

it('refuses to complete a claim nobody opened', function (): void {
    expect(fn () => (new CompleteGuestOrderClaim())('clm_nope', 'x'))->toThrow(ClaimNotClaimable::class);
});

it('keeps attempts append-only', function (): void {
    verifier();
    (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'buyer@example.test', 'person-1');

    $attempt = ClaimAttempt::query()->first();

    expect(fn () => $attempt->update(['outcome' => ClaimAttemptOutcome::Granted]))->toThrow(AttemptsAreAppendOnly::class);
    expect(fn () => $attempt->delete())->toThrow(AttemptsAreAppendOnly::class);
});

it('announces the opening without putting the secret on the event', function (): void {
    Event::fake();
    verifier();

    $proof = (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'buyer@example.test', 'person-1');

    Event::assertDispatched(GuestOrderClaimOpened::class, function (GuestOrderClaimOpened $e) use ($proof): bool {
        return $e->claimReference === $proof->claimReference
            && ! str_contains(json_encode(get_object_vars($e)), $proof->token);
    });
});

it('announces the grant', function (): void {
    verifier();
    $proof = (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'buyer@example.test', 'person-1');

    Event::fake();
    (new CompleteGuestOrderClaim())($proof->claimReference, $proof->token);

    Event::assertDispatched(GuestOrderClaimGranted::class);
});
