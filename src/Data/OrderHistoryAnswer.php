<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

use JsonSerializable;

/**
 * Three answers: a list, an empty list, and no answer at all.
 *
 * The middle and the last are different facts about a person and a surface must
 * render them differently. "You have not ordered anything" and "we cannot reach
 * the orders system" are the same pixels only if somebody decided to make them
 * the same, and nobody ever decides that on purpose — it happens because `[]` is
 * what an unbound seam returns if you let it.
 */
final readonly class OrderHistoryAnswer implements JsonSerializable
{
    /** @param list<OrderSummary> $orders */
    private function __construct(
        public bool $available,
        public array $orders,
    ) {}

    /** @param list<OrderSummary> $orders */
    public static function of(array $orders): self
    {
        return new self(true, $orders);
    }

    public static function unavailable(): self
    {
        return new self(false, []);
    }

    public function isEmpty(): bool
    {
        return $this->available && $this->orders === [];
    }

    /** @return array{available: bool, orders: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'orders' => array_map(fn (OrderSummary $o): array => $o->toArray(), $this->orders),
        ];
    }

    /** @return array{available: bool, orders: list<array<string, mixed>>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
