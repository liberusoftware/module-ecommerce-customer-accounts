<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Tests\Fakes;

use Liberu\Ecommerce\CustomerAccounts\Contracts\VerifiesGuestOrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Data\ClaimVerification;

/** Orders, standing in. Keyed on (tenant, reference, address) exactly as the real one must be. */
final class FakeClaimVerifier implements VerifiesGuestOrderClaim
{
    /** @param list<array{0: string, 1: string, 2: string}> $orders */
    public function __construct(
        private readonly array $orders = [],
        private readonly bool $available = true,
    ) {}

    public function verifyClaim(string $tenantId, string $orderReference, string $email): ClaimVerification
    {
        if (! $this->available) {
            return ClaimVerification::unavailable();
        }

        foreach ($this->orders as [$tenant, $reference, $address]) {
            if ($tenant === $tenantId && $reference === $orderReference && mb_strtolower($address) === mb_strtolower($email)) {
                return ClaimVerification::matched();
            }
        }

        return ClaimVerification::notMatched();
    }
}
