<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Actions;

use Liberu\Ecommerce\CustomerAccounts\Exceptions\SavedListNotFound;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedList;

/** @return int how many rows went; 0 when the product was not on the list */
final class RemoveItemFromSavedList
{
    public function __invoke(string $listReference, string $productRef): int
    {
        $list = SavedList::query()->where('reference', $listReference)->first();

        if ($list === null) {
            throw SavedListNotFound::referenced($listReference);
        }

        return $list->items()->where('product_ref', $productRef)->delete();
    }
}
