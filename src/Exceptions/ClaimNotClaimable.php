<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Exceptions;

use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimState;

final class ClaimNotClaimable extends CustomerAccountsException
{
    public static function in(string $reference, ClaimState $state): self
    {
        return new self("Claim [{$reference}] is [{$state->value}] and cannot be completed.");
    }

    public static function notFound(string $reference): self
    {
        return new self("No claim [{$reference}] exists.");
    }

    public static function expired(string $reference): self
    {
        return new self("Claim [{$reference}] has expired.");
    }
}
