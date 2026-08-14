<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Contracts;

use Liberu\Ecommerce\CustomerAccounts\Data\OrderHistoryAnswer;

/**
 * "What has this person bought from this merchant, and what is each order's
 * reference?"
 *
 * Orders' to bind. This module owns the *claim* that makes a past order visible
 * to a person; it does not own the order, hold a copy of one, or read Orders'
 * tables to answer. The reason it takes this seam at all is that a claim is
 * worth nothing if there is no way to show what it entitles.
 *
 * Unbound, the answer is **unavailable**, and a surface renders "not available"
 * rather than an empty list. An empty list is indistinguishable from having
 * never bought anything, and telling a person who has ordered nine times that
 * they have ordered nothing is a wrong answer rather than a missing one. This
 * rule has now recurred in four waves and it recurs because rendering `[]` is
 * always the easier code.
 *
 * `tenantId` first, opaque `personRef` second — the same shape and the same
 * subject vocabulary as wave 13's ResolvesPurchaseHistory, so one host adapter
 * can pass one value to both.
 */
interface ResolvesOrderHistory
{
    public function historyFor(string $tenantId, string $personRef): OrderHistoryAnswer;
}
