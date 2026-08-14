<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Exceptions;

use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;

final class RequestAlreadyConcluded extends CustomerAccountsException
{
    public static function in(string $reference, RequestState $state): self
    {
        return new self("Privacy request [{$reference}] is already concluded as [{$state->value}].");
    }
}
