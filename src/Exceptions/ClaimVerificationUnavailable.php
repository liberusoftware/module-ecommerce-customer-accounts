<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Exceptions;

/**
 * Nothing verified the claim, so nothing is claimed.
 *
 * Distinct from a refusal, and it must stay distinct: this one means the feature
 * is unavailable and a retry may work, while a refusal is a permanent answer
 * about this reference and this address. A surface that renders them the same
 * either tells a legitimate customer they are lying, or invites an enumerator to
 * keep trying.
 */
final class ClaimVerificationUnavailable extends CustomerAccountsException
{
    public static function forTenant(string $tenantId): self
    {
        return new self("No VerifiesGuestOrderClaim is bound; guest order claiming is closed for tenant [{$tenantId}].");
    }
}
