<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

use JsonSerializable;

/** A person's list at one merchant, with its items and how many live shares point at it. */
final readonly class SavedListView implements JsonSerializable
{
    /** @param list<SavedListItemView> $items */
    public function __construct(
        public string $reference,
        public string $name,
        public string $ownerRef,
        public array $items,
        public int $liveShares,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'name' => $this->name,
            'owner_ref' => $this->ownerRef,
            'live_shares' => $this->liveShares,
            'items' => array_map(fn (SavedListItemView $i): array => $i->toArray(), $this->items),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
