<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Actions;

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\InvalidQuantity;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\SavedListNotFound;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedList;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedListItem;

/**
 * A product reference and a quantity. No price is accepted, stored or computed —
 * not the price now, and especially not the price when it was saved, which is
 * the one a list is most often asked to remember and the one it has no standing
 * to assert.
 */
final class AddItemToSavedList
{
    public function __invoke(string $listReference, string $productRef, int $quantity = 1, ?string $note = null): SavedListItem
    {
        if ($quantity < 1) {
            throw InvalidQuantity::of($quantity);
        }

        $list = SavedList::query()->where('reference', $listReference)->first();

        if ($list === null) {
            throw SavedListNotFound::referenced($listReference);
        }

        $item = $list->items()->where('product_ref', $productRef)->first();

        if ($item instanceof SavedListItem) {
            $item->forceFill(['quantity' => $quantity, 'note' => $note])->save();

            return $item;
        }

        $created = $list->items()->create([
            'tenant_id' => $list->tenant_id,
            'product_ref' => $productRef,
            'quantity' => $quantity,
            'note' => $note,
            'added_at' => CarbonImmutable::now(),
        ]);

        return $created;
    }
}
