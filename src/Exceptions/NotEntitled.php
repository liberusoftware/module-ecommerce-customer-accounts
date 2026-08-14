<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Exceptions;

/**
 * The standing to act is absent.
 *
 * One class for "not yours" whatever the thing is, because a surface must render
 * all of them the same: the host's own order lookup scoped the query to the
 * user's own orders so a foreign order was a 404 rather than a 403, which is the
 * ownership check and the not-an-oracle property in one line. That instinct is
 * right and this exception exists so it does not have to be re-had per surface.
 */
final class NotEntitled extends CustomerAccountsException
{
    public static function toOrder(string $orderReference): self
    {
        return new self("No granted claim entitles this person to order [{$orderReference}].");
    }

    public static function toList(string $listReference): self
    {
        return new self("Saved list [{$listReference}] does not belong to this person at this merchant.");
    }

    public static function toRequest(string $requestReference): self
    {
        return new self("Privacy request [{$requestReference}] does not belong to this person.");
    }
}
