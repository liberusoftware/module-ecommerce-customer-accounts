<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

use Carbon\CarbonImmutable;
use JsonSerializable;

/**
 * One line of a person's order list, as Orders hands it over.
 *
 * **No money.** Not a total, not a currency, not a minor-unit integer. This
 * module may not assert what an order was worth — it does not hold the order and
 * a figure restated here forks the definition of the figure. A surface that
 * wants a total asks Orders for it, already shaped.
 */
final readonly class OrderSummary implements JsonSerializable
{
    public function __construct(
        public string $orderReference,
        public CarbonImmutable $placedAt,
        public ?string $status = null,
    ) {}

    /** @return array{order_reference: string, placed_at: string, status: string|null} */
    public function toArray(): array
    {
        return [
            'order_reference' => $this->orderReference,
            'placed_at' => $this->placedAt->toIso8601String(),
            'status' => $this->status,
        ];
    }

    /** @return array{order_reference: string, placed_at: string, status: string|null} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
