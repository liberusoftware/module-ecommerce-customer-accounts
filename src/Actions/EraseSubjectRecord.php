<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimState;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Events\ShareRevoked;
use Liberu\Ecommerce\CustomerAccounts\Events\SubjectRecordErased;
use Liberu\Ecommerce\CustomerAccounts\Models\OrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedList;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedListShare;

/**
 * This module erasing **its own** rows, and nothing else's.
 *
 * It is not registered as a participant of itself. A coordinator that appeared
 * in its own registry could report its own erasure as one more green tick, and
 * would stop erasing itself the moment somebody edited the entry out.
 *
 * Three things happen and the first is the one the host forgot. **Shares are
 * revoked**: the host's `forceFill()` scrubbed the name, the email, the
 * verification, the password, the remember token and both 2FA columns, and left
 * `wishlist_share_token` sitting there — so a URL published to third parties
 * still resolved to the erased user's row. A live token pointing at an erased
 * subject is the failure that outlives the request.
 *
 * Lists and their items are deleted; they are the person's own saved data and
 * there is no shape worth keeping. Claims are **redacted** rather than deleted,
 * because a claim is the record of somebody having been let into an order, and
 * an unexplained grant is a worse audit outcome than a redacted one.
 *
 * Claim *attempts* are untouched, and deliberately so: they carry no subject
 * reference, only a one-way fingerprint of an address that was typed at a form,
 * and they are the only evidence that anybody tried to enumerate this
 * merchant's orders. They are append-only in the model for the same reason.
 */
final class EraseSubjectRecord
{
    public function __invoke(string $subjectRef, RequestScope $scope, ?string $tenantId = null): SubjectRecordErased
    {
        $confine = static function (mixed $query) use ($scope, $tenantId): void {
            if ($scope === RequestScope::Tenant && $tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }
        };

        $erased = DB::transaction(function () use ($subjectRef, $confine): array {
            $lists = SavedList::query()->where('owner_ref', $subjectRef)->tap($confine)->get();
            $shares = SavedListShare::query()->where('owner_ref', $subjectRef)->tap($confine)->get();

            $revoked = 0;

            foreach ($shares as $share) {
                if ($share->isLive()) {
                    $revoked++;
                }

                // The revocation is announced before the row goes: deleting the
                // token is the strongest possible revocation, and a listener
                // that mirrors shares somewhere else still needs to be told.
                Event::dispatch(new ShareRevoked(
                    shareReference: $share->reference,
                    tenantId: $share->tenant_id,
                    listReference: (string) $share->list?->reference,
                    reason: 'subject_erased',
                ));

                $share->delete();
            }

            foreach ($lists as $list) {
                $list->items()->delete();
                $list->delete();
            }

            $claims = OrderClaim::query()->where('claimant_ref', $subjectRef)->tap($confine)->get();

            foreach ($claims as $claim) {
                $claim->forceFill([
                    'claimant_ref' => null,
                    'email_fingerprint' => null,
                    'token_fingerprint' => null,
                    'state' => ClaimState::Revoked,
                    'closed_at' => CarbonImmutable::now(),
                ])->save();
            }

            return [$lists->count(), $revoked, $claims->count()];
        });

        $event = new SubjectRecordErased(
            subjectRef: $subjectRef,
            scope: $scope,
            tenantId: $tenantId,
            listsDeleted: $erased[0],
            sharesRevoked: $erased[1],
            claimsRedacted: $erased[2],
        );

        Event::dispatch($event);

        return $event;
    }
}
