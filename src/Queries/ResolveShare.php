<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Queries;

use Liberu\Ecommerce\CustomerAccounts\Data\SavedListItemView;
use Liberu\Ecommerce\CustomerAccounts\Data\SavedListView;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedListItem;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedListShare;
use Liberu\Ecommerce\CustomerAccounts\Support\Fingerprint;

/**
 * A share token to the list it shares, or nothing.
 *
 * Nothing covers revoked, unknown and belonging-to-another-merchant, and it
 * covers them identically — a share route is public and unauthenticated
 * wherever it is mounted, so it must not distinguish a token that was revoked
 * from one that never existed.
 *
 * The optional `tenantId` is how a storefront asks "is this share mine to
 * show?". Passing it is the honest thing for a public route to do; omitting it
 * is for an operator tool that is deliberately looking across merchants.
 */
final class ResolveShare
{
    public function __invoke(string $token, ?string $tenantId = null): ?SavedListView
    {
        $share = SavedListShare::query()
            ->where('token_fingerprint', Fingerprint::ofSecret($token))
            ->whereNull('revoked_at')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->first();

        $list = $share?->list;

        if ($list === null || $list->tenant_id !== $share?->tenant_id) {
            return null;
        }

        $items = $list->items()
            ->orderBy('id')
            ->get()
            ->map(fn (SavedListItem $i): SavedListItemView => new SavedListItemView(
                productRef: $i->product_ref,
                quantity: $i->quantity,
                note: $i->note,
                addedAt: $i->added_at,
            ))
            ->values()
            ->all();

        return new SavedListView(
            reference: $list->reference,
            name: $list->name,
            ownerRef: $list->owner_ref,
            items: $items,
            liveShares: $list->liveShares()->count(),
        );
    }
}
