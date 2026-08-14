<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Exceptions;

/**
 * Too many attempts. The limit lives in the domain rather than in one surface's
 * middleware, so every surface inherits it and none can forget it.
 */
final class ClaimRateLimited extends CustomerAccountsException
{
    public static function after(int $attempts, int $windowMinutes): self
    {
        return new self("Too many claim attempts ({$attempts}) in the last {$windowMinutes} minutes.");
    }
}
