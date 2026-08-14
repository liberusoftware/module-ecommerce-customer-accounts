<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CustomerAccounts\Actions\AddItemToSavedList;
use Liberu\Ecommerce\CustomerAccounts\Actions\CommissionParticipant;
use Liberu\Ecommerce\CustomerAccounts\Actions\CompleteGuestOrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Actions\ConcludePrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Actions\CreateSavedList;
use Liberu\Ecommerce\CustomerAccounts\Actions\EraseSubjectRecord;
use Liberu\Ecommerce\CustomerAccounts\Actions\OpenGuestOrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Actions\ShareSavedList;
use Liberu\Ecommerce\CustomerAccounts\Contracts\VerifiesGuestOrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimState;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Events\ShareRevoked;
use Liberu\Ecommerce\CustomerAccounts\Events\SubjectRecordErased;
use Liberu\Ecommerce\CustomerAccounts\Models\ClaimAttempt;
use Liberu\Ecommerce\CustomerAccounts\Models\OrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedList;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedListItem;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedListShare;
use Liberu\Ecommerce\CustomerAccounts\Queries\ResolveShare;
use Liberu\Ecommerce\CustomerAccounts\Tests\Fakes\FakeClaimVerifier;
use Liberu\Ecommerce\CustomerAccounts\Tests\Fakes\RecordingParticipant;

function someoneWithEverything(string $tenant = 'tenant-a', string $person = 'person-1'): string
{
    App::instance(VerifiesGuestOrderClaim::class, new FakeClaimVerifier([
        ['tenant-a', 'ORD-1001', 'p1@example.test'],
        ['tenant-b', 'ORD-2002', 'p1@example.test'],
    ]));

    $list = (new CreateSavedList())($tenant, $person, 'Wishlist');
    (new AddItemToSavedList())($list->reference, 'prod-9');
    $share = (new ShareSavedList())($list->reference);

    $reference = $tenant === 'tenant-a' ? 'ORD-1001' : 'ORD-2002';
    $claim = (new OpenGuestOrderClaim())($tenant, $reference, 'p1@example.test', $person);
    (new CompleteGuestOrderClaim())($claim->claimReference, $claim->token);

    return $share->token;
}

it('revokes every share the subject holds — the thing the host forgot', function (): void {
    $token = someoneWithEverything();

    expect((new ResolveShare())($token))->not->toBeNull();

    (new EraseSubjectRecord())('person-1', RequestScope::Everywhere);

    // The host scrubbed the name, the email, the password, the remember token
    // and both 2FA columns, and left the share token — so a URL published to
    // third parties still resolved to the erased user's row.
    expect((new ResolveShare())($token))->toBeNull()
        ->and(SavedListShare::query()->count())->toBe(0);
});

it('deletes the lists and their items', function (): void {
    someoneWithEverything();

    (new EraseSubjectRecord())('person-1', RequestScope::Everywhere);

    expect(SavedList::query()->count())->toBe(0)
        ->and(SavedListItem::query()->count())->toBe(0);
});

it('redacts the claims rather than deleting them', function (): void {
    someoneWithEverything();

    (new EraseSubjectRecord())('person-1', RequestScope::Everywhere);

    $claim = OrderClaim::query()->first();

    // The grant is an audit record of somebody having been let into an order.
    // The person goes; the shape stays.
    expect(OrderClaim::query()->count())->toBe(1)
        ->and($claim->claimant_ref)->toBeNull()
        ->and($claim->email_fingerprint)->toBeNull()
        ->and($claim->token_fingerprint)->toBeNull()
        ->and($claim->state)->toBe(ClaimState::Revoked)
        ->and($claim->order_reference)->toBe('ORD-1001');
});

it('leaves the attempt record alone, because it is the security signal', function (): void {
    someoneWithEverything();
    $before = ClaimAttempt::query()->count();

    (new EraseSubjectRecord())('person-1', RequestScope::Everywhere);

    // Attempts carry no subject reference — only a one-way fingerprint of an
    // address that was typed at a form — and they are the only evidence that
    // anybody tried to enumerate this merchant's orders.
    expect(ClaimAttempt::query()->count())->toBe($before);
});

it('erases only one merchant when the case said one merchant', function (): void {
    someoneWithEverything('tenant-a');
    someoneWithEverything('tenant-b');

    (new EraseSubjectRecord())('person-1', RequestScope::Tenant, 'tenant-a');

    expect(SavedList::query()->pluck('tenant_id')->all())->toBe(['tenant-b'])
        ->and(SavedListShare::query()->pluck('tenant_id')->all())->toBe(['tenant-b'])
        ->and(OrderClaim::query()->whereNull('claimant_ref')->count())->toBe(1);
});

it('announces what it cleared', function (): void {
    someoneWithEverything();
    Event::fake();

    (new EraseSubjectRecord())('person-1', RequestScope::Everywhere);

    Event::assertDispatched(SubjectRecordErased::class, fn (SubjectRecordErased $e): bool => $e->listsDeleted === 1
        && $e->sharesRevoked === 1
        && $e->claimsRedacted === 1);

    Event::assertDispatched(ShareRevoked::class, fn (ShareRevoked $e): bool => $e->reason === 'subject_erased');
});

it('clears this module s own rows when an erasure case concludes', function (): void {
    $token = someoneWithEverything();
    participants(['customers' => ['adapter' => new RecordingParticipant()]]);

    $request = openCase(scope: RequestScope::Everywhere);
    (new CommissionParticipant())($request->reference, 'customers');

    expect((new ResolveShare())($token))->toBeNull()
        ->and(SavedList::query()->count())->toBe(0);
});

it('clears its own rows on a partial conclusion too', function (): void {
    $token = someoneWithEverything();
    participants(['orders' => ['adapter' => null]]);

    $request = openCase(scope: RequestScope::Everywhere);
    (new CommissionParticipant())($request->reference, 'orders');
    (new ConcludePrivacyRequest())($request->reference);

    // Our rows are ours to clear whatever the other participants managed.
    expect((new ResolveShare())($token))->toBeNull();
});

it('clears nothing of its own for an access case', function (): void {
    $token = someoneWithEverything();
    participants(['customers' => ['adapter' => new RecordingParticipant(payload: [])]]);

    $request = openCase(kind: RequestKind::Access);
    (new CommissionParticipant())($request->reference, 'customers');

    expect((new ResolveShare())($token))->not->toBeNull();
});
