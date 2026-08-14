<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Tests\Fakes;

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\CustomerAccounts\Contracts\ResolvesOrderHistory;
use Liberu\Ecommerce\CustomerAccounts\Data\OrderHistoryAnswer;
use Liberu\Ecommerce\CustomerAccounts\Data\OrderSummary;

final class FakeOrderHistory implements ResolvesOrderHistory
{
    /** @param array<string, list<string>> $byTenant tenant => order references */
    public function __construct(private readonly array $byTenant = []) {}

    public function historyFor(string $tenantId, string $personRef): OrderHistoryAnswer
    {
        return OrderHistoryAnswer::of(array_map(
            fn (string $reference): OrderSummary => new OrderSummary($reference, CarbonImmutable::parse('2026-01-01T00:00:00Z'), 'paid'),
            $this->byTenant[$tenantId] ?? [],
        ));
    }
}
