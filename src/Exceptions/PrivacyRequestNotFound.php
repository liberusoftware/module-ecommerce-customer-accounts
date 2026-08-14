<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Exceptions;

final class PrivacyRequestNotFound extends CustomerAccountsException
{
    public static function referenced(string $reference): self
    {
        return new self("No privacy request [{$reference}] exists.");
    }
}
