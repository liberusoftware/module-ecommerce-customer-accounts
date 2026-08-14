<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Queries;

use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimState;
use Liberu\Ecommerce\CustomerAccounts\Models\OrderClaim;

/**
 * "May this person see this order?" — **derived** from a granted claim, every
 * time, and never stored as a permission.
 *
 * A permission row outlives the reason for it. Evidence does not: revoke the
 * claim and the entitlement is gone, because there was never a second place
 * recording that it had been granted.
 *
 * Scoped to one merchant because an order reference is unique only within one. A
 * claim made at merchant A entitles the claimant to nothing at merchant B, even
 * when both merchants happen to have an order numbered 1001 — which they will.
 */
final class IsEntitledToOrder
{
    public function __invoke(string $tenantId, string $claimantRef, string $orderReference): bool
    {
        return OrderClaim::query()
            ->where('tenant_id', $tenantId)
            ->where('claimant_ref', $claimantRef)
            ->where('order_reference', $orderReference)
            ->where('state', ClaimState::Granted)
            ->exists();
    }
}
