<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

use Carbon\CarbonImmutable;
use JsonSerializable;

/**
 * A product reference and a quantity. **No price, ever** — not the price now and
 * especially not the price when it was saved. A list holds references and
 * survives a repricing; a cart holds priced lines and does not. They are
 * different aggregates and this module builds only the first.
 */
final readonly class SavedListItemView implements JsonSerializable
{
    public function __construct(
        public string $productRef,
        public int $quantity,
        public ?string $note,
        public CarbonImmutable $addedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'product_ref' => $this->productRef,
            'quantity' => $this->quantity,
            'note' => $this->note,
            'added_at' => $this->addedAt->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
