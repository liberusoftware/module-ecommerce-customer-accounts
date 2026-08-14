<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Exceptions;

final class AttemptsAreAppendOnly extends CustomerAccountsException
{
    public static function cannotUpdate(int $id): self
    {
        return new self("Claim attempt [{$id}] is a record of what happened and cannot be updated.");
    }

    public static function cannotDelete(int $id): self
    {
        return new self("Claim attempt [{$id}] is a record of what happened and cannot be deleted.");
    }
}
