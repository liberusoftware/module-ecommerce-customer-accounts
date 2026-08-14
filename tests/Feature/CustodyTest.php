<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CustomerAccounts\Actions\AddItemToSavedList;
use Liberu\Ecommerce\CustomerAccounts\Actions\CommissionParticipant;
use Liberu\Ecommerce\CustomerAccounts\Actions\CompleteGuestOrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Actions\CreateSavedList;
use Liberu\Ecommerce\CustomerAccounts\Actions\OpenGuestOrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Actions\ShareSavedList;
use Liberu\Ecommerce\CustomerAccounts\Contracts\VerifiesGuestOrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Enums\Channel;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimAttemptOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ClaimRefused;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\NotEntitled;
use Liberu\Ecommerce\CustomerAccounts\Models\ClaimAttempt;
use Liberu\Ecommerce\CustomerAccounts\Models\OrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Policies\CustodyPolicy;
use Liberu\Ecommerce\CustomerAccounts\Queries\IsEntitledToOrder;
use Liberu\Ecommerce\CustomerAccounts\Queries\ListSavedLists;
use Liberu\Ecommerce\CustomerAccounts\Queries\ResolveShare;
use Liberu\Ecommerce\CustomerAccounts\Support\Fingerprint;
use Liberu\Ecommerce\CustomerAccounts\Tests\Fakes\FakeClaimVerifier;
use Liberu\Ecommerce\CustomerAccounts\Tests\Fakes\RecordingParticipant;

/**
 * Two merchants, and — the part that matters — the *same reference* at both.
 * Every leak this file exists to catch needs a collision to be visible at all,
 * and the test that catches it is always the one with two merchants.
 */
function bothMerchants(): void
{
    App::instance(VerifiesGuestOrderClaim::class, new FakeClaimVerifier([
        ['tenant-a', 'ORD-1001', 'alice@example.test'],
        ['tenant-b', 'ORD-1001', 'bob@example.test'],
    ]));
}

it('entitles a claim made at one merchant to nothing at the other', function (): void {
    bothMerchants();

    $proof = (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'alice@example.test', 'person-1');
    (new CompleteGuestOrderClaim())($proof->claimReference, $proof->token);

    expect((new IsEntitledToOrder())('tenant-a', 'person-1', 'ORD-1001'))->toBeTrue()
        ->and((new IsEntitledToOrder())('tenant-b', 'person-1', 'ORD-1001'))->toBeFalse();

    CustodyPolicy::assertEntitledToOrder('tenant-a', 'person-1', 'ORD-1001');
    expect(fn () => CustodyPolicy::assertEntitledToOrder('tenant-b', 'person-1', 'ORD-1001'))
        ->toThrow(NotEntitled::class);
});

it('stops two people with orders of the same number claiming each other s', function (): void {
    bothMerchants();

    $alice = (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'alice@example.test', 'alice');
    (new CompleteGuestOrderClaim())($alice->claimReference, $alice->token);

    // Bob's address is right for his own merchant and wrong for Alice's, and the
    // refusal says nothing about which.
    expect(fn () => (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'bob@example.test', 'bob'))
        ->toThrow(ClaimRefused::class);

    expect((new IsEntitledToOrder())('tenant-a', 'bob', 'ORD-1001'))->toBeFalse();
});

it('keeps a claim s attempts relation inside its own merchant', function (): void {
    // The relation joins on order_reference — a reference from another module,
    // unique only within a merchant. This is the relation, not the query, and
    // wave 14 found the previous proof was written about queries.
    bothMerchants();

    $proof = (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'alice@example.test', 'alice');
    (new OpenGuestOrderClaim())('tenant-b', 'ORD-1001', 'bob@example.test', 'bob');

    $claim = OrderClaim::query()->where('reference', $proof->claimReference)->first();

    expect(ClaimAttempt::query()->where('order_reference', 'ORD-1001')->count())->toBe(2)
        ->and($claim->attempts()->count())->toBe(1)
        ->and($claim->attempts()->first()->tenant_id)->toBe('tenant-a');
});

it('keeps a saved list and its relations inside its own merchant', function (): void {
    $a = (new CreateSavedList())('tenant-a', 'person-1', 'Wishlist');
    $b = (new CreateSavedList())('tenant-b', 'person-1', 'Wishlist');

    (new AddItemToSavedList())($a->reference, 'prod-9');
    (new AddItemToSavedList())($b->reference, 'prod-9');
    (new ShareSavedList())($b->reference);

    expect($a->items()->count())->toBe(1)
        ->and($a->shares()->count())->toBe(0)
        ->and($a->liveShares()->count())->toBe(0)
        ->and((new ListSavedLists())('tenant-a', 'person-1'))->toHaveCount(1);
});

it('hides a share from the merchant it does not belong to', function (): void {
    $b = (new CreateSavedList())('tenant-b', 'person-1', 'Wishlist');
    $proof = (new ShareSavedList())($b->reference);

    expect((new ResolveShare())($proof->token, 'tenant-b'))->not->toBeNull()
        ->and((new ResolveShare())($proof->token, 'tenant-a'))->toBeNull();
});

it('refuses a list to somebody who is not its owner at this merchant', function (): void {
    $list = (new CreateSavedList())('tenant-a', 'person-1', 'Wishlist');

    expect(CustodyPolicy::ownsList($list, 'tenant-a', 'person-1'))->toBeTrue()
        ->and(CustodyPolicy::ownsList($list, 'tenant-b', 'person-1'))->toBeFalse()
        ->and(CustodyPolicy::ownsList($list, 'tenant-a', 'person-2'))->toBeFalse();

    expect(fn () => CustodyPolicy::assertOwnsList($list, 'tenant-b', 'person-1'))->toThrow(NotEntitled::class);
});

it('confines a tenant-scoped case and lets an everywhere-scoped one cross', function (): void {
    participants([]);

    $confined = openCase(scope: RequestScope::Tenant, tenantId: 'tenant-a');
    $global = openCase(scope: RequestScope::Everywhere, tenantId: 'tenant-a');

    // The one deliberate deployment-wide exception in this module, tested rather
    // than assumed: scope is a stored field precisely so this stays visible.
    expect(CustodyPolicy::isSubjectOf($confined, 'person-1', 'tenant-a'))->toBeTrue()
        ->and(CustodyPolicy::isSubjectOf($confined, 'person-1', 'tenant-b'))->toBeFalse()
        ->and(CustodyPolicy::isSubjectOf($global, 'person-1', 'tenant-b'))->toBeTrue()
        ->and(CustodyPolicy::isSubjectOf($global, 'person-2', 'tenant-a'))->toBeFalse();

    expect(fn () => CustodyPolicy::assertIsSubjectOf($confined, 'person-1', 'tenant-b'))->toThrow(NotEntitled::class);
});

it('records the scope on every participant answer of a tenant-scoped case', function (): void {
    participants([
        'customers' => ['adapter' => new RecordingParticipant(scopeApplied: RequestScope::Everywhere)],
        'reviews' => ['adapter' => new RecordingParticipant(scopeApplied: RequestScope::Tenant)],
    ]);

    $request = openCase(scope: RequestScope::Tenant);
    (new CommissionParticipant())($request->reference, 'customers');
    (new CommissionParticipant())($request->reference, 'reviews');

    $answers = $request->participants()->orderBy('participant')->get();

    expect($answers->pluck('scope_applied')->all())->toBe([RequestScope::Everywhere, RequestScope::Tenant])
        ->and($answers->pluck('scope_mismatch')->all())->toBe([true, false]);
});

it('groups attempts by a fingerprint that is not the address', function (): void {
    bothMerchants();

    (new OpenGuestOrderClaim())('tenant-a', 'ORD-1001', 'Alice@Example.test', 'alice', Channel::Web, CarbonImmutable::now());

    $attempt = ClaimAttempt::query()->first();

    expect($attempt->email_fingerprint)->toBe(Fingerprint::of('alice@example.test'))
        ->and($attempt->outcome)->toBe(ClaimAttemptOutcome::Issued);
});
