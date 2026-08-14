<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Exceptions;

final class SavedListNotFound extends CustomerAccountsException
{
    public static function referenced(string $reference): self
    {
        return new self("No saved list [{$reference}] exists.");
    }
}
