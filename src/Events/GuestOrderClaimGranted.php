<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Events;

final readonly class GuestOrderClaimGranted
{
    public function __construct(
        public string $claimReference,
        public string $tenantId,
        public string $orderReference,
        public string $claimantRef,
    ) {}
}
