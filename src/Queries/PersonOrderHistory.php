<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Queries;

use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CustomerAccounts\Contracts\ResolvesOrderHistory;
use Liberu\Ecommerce\CustomerAccounts\Data\OrderHistoryAnswer;

/**
 * The person's orders, asked of Orders rather than read from it.
 *
 * Unbound is **unavailable**, not empty. This is the fourth wave in which that
 * sentence has had to be written down, and it keeps recurring because returning
 * `[]` is always the shorter code and always looks like it works.
 */
final class PersonOrderHistory
{
    public function __invoke(string $tenantId, string $personRef): OrderHistoryAnswer
    {
        if (! App::bound(ResolvesOrderHistory::class)) {
            return OrderHistoryAnswer::unavailable();
        }

        $resolver = App::make(ResolvesOrderHistory::class);

        if (! $resolver instanceof ResolvesOrderHistory) {
            return OrderHistoryAnswer::unavailable();
        }

        return $resolver->historyFor($tenantId, $personRef);
    }
}
