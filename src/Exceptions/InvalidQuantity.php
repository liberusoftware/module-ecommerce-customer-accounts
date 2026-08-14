<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Exceptions;

final class InvalidQuantity extends CustomerAccountsException
{
    public static function of(int $quantity): self
    {
        return new self("A saved list item quantity must be at least 1; [{$quantity}] given.");
    }
}
