<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Exceptions;

use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;

final class WrongRequestKind extends CustomerAccountsException
{
    public static function expected(string $reference, RequestKind $expected, RequestKind $actual): self
    {
        return new self("Privacy request [{$reference}] is an [{$actual->value}] request; this needs an [{$expected->value}] one.");
    }
}
