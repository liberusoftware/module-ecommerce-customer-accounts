<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Queries;

use Liberu\Ecommerce\CustomerAccounts\Data\SavedListItemView;
use Liberu\Ecommerce\CustomerAccounts\Data\SavedListView;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedList;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedListItem;

/** A person's lists at one merchant. The tenant is required, not optional. */
final class ListSavedLists
{
    /** @return list<SavedListView> */
    public function __invoke(string $tenantId, string $ownerRef): array
    {
        return SavedList::query()
            ->where('tenant_id', $tenantId)
            ->where('owner_ref', $ownerRef)
            ->orderBy('name')
            ->get()
            ->map(fn (SavedList $list): SavedListView => new SavedListView(
                reference: $list->reference,
                name: $list->name,
                ownerRef: $list->owner_ref,
                items: $list->items()
                    ->orderBy('id')
                    ->get()
                    ->map(fn (SavedListItem $i): SavedListItemView => new SavedListItemView(
                        productRef: $i->product_ref,
                        quantity: $i->quantity,
                        note: $i->note,
                        addedAt: $i->added_at,
                    ))
                    ->values()
                    ->all(),
                liveShares: $list->liveShares()->count(),
            ))
            ->values()
            ->all();
    }
}
