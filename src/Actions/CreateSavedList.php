<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Actions;

use Liberu\Ecommerce\CustomerAccounts\Models\SavedList;
use Liberu\Ecommerce\CustomerAccounts\Support\Reference;

/**
 * A list belongs to one person at one merchant, and the merchant is part of its
 * identity rather than a scope applied to a lookup afterwards.
 *
 * The host's `firstOrCreate(['user_id', 'product_id'])` ran under a store scope
 * that narrows when a store is in context and applies **no predicate at all**
 * when it is not — so off a storefront the lookup matched any merchant's row and
 * the add silently resolved into a different merchant's wishlist. A scope that
 * can be absent is not an identity.
 */
final class CreateSavedList
{
    public function __invoke(string $tenantId, string $ownerRef, string $name): SavedList
    {
        return SavedList::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'owner_ref' => $ownerRef, 'name' => $name],
            ['reference' => Reference::mint('lst')],
        );
    }
}
