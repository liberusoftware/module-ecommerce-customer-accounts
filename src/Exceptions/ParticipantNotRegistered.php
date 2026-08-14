<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Exceptions;

/**
 * Asked about a participant nobody registered.
 *
 * This is loud rather than lenient on purpose. A silent "no such participant, so
 * nothing to do" is how a module's rows survive an erasure that reported
 * success — the registry is the only place that knows a module exists, and a
 * typo in it must be a failure rather than an absence.
 */
final class ParticipantNotRegistered extends CustomerAccountsException
{
    public static function named(string $name): self
    {
        return new self("No participant [{$name}] is registered in customer-accounts.participants.");
    }
}
