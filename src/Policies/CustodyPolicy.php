<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Policies;

use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\NotEntitled;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedList;
use Liberu\Ecommerce\CustomerAccounts\Queries\IsEntitledToOrder;

/**
 * Standing, in one place, so no surface has to work it out again.
 *
 * The host worked it out four times and got a different answer each time.
 * `ReturnRequest::customer_id` is a `belongsTo(User::class, 'customer_id')` and
 * the controller authorised with
 * `abort_unless((int) $returnRequest->customer_id === (int) $request->user()->id)`
 * — so a column named for the customers table, holding a user id, was the
 * authorization check. Wave 13 recorded `customer_id` meaning four different
 * things across that application; this is the instance where the ambiguity
 * decides who sees somebody else's return.
 *
 * **Every check takes the tenant.** Standing at merchant A is not standing at
 * merchant B, and a check that forgets to say which merchant it is asking about
 * is a check that passes at both.
 */
final class CustodyPolicy
{
    public static function entitledToOrder(string $tenantId, string $claimantRef, string $orderReference): bool
    {
        return (new IsEntitledToOrder())($tenantId, $claimantRef, $orderReference);
    }

    public static function assertEntitledToOrder(string $tenantId, string $claimantRef, string $orderReference): void
    {
        if (! self::entitledToOrder($tenantId, $claimantRef, $orderReference)) {
            throw NotEntitled::toOrder($orderReference);
        }
    }

    public static function ownsList(SavedList $list, string $tenantId, string $ownerRef): bool
    {
        return $list->tenant_id === $tenantId && $list->owner_ref === $ownerRef;
    }

    public static function assertOwnsList(SavedList $list, string $tenantId, string $ownerRef): void
    {
        if (! self::ownsList($list, $tenantId, $ownerRef)) {
            throw NotEntitled::toList($list->reference);
        }
    }

    /**
     * A case belongs to its subject.
     *
     * Note that it is **not** scoped by tenant: a request whose scope is
     * `everywhere` is deployment-wide and its subject can read it from wherever
     * they are standing. This is the deliberate exception to per-merchant
     * confinement, and it is the reason scope is a stored field rather than a
     * convention — a tenant-scoped case stays tenant-scoped here.
     */
    public static function isSubjectOf(PrivacyRequest $request, string $subjectRef, ?string $tenantId = null): bool
    {
        if ($request->subject_ref !== $subjectRef) {
            return false;
        }

        return $request->scope === RequestScope::Everywhere
            || $tenantId === null
            || $request->tenant_id === $tenantId;
    }

    public static function assertIsSubjectOf(PrivacyRequest $request, string $subjectRef, ?string $tenantId = null): void
    {
        if (! self::isSubjectOf($request, $subjectRef, $tenantId)) {
            throw NotEntitled::toRequest($request->reference);
        }
    }
}
