<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Events;

use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimAttemptOutcome;

/**
 * A refusal, with the outcome that produced it. The order reference is on it
 * because an operator watching for enumeration needs to see what was tried; the
 * address is not, because it is held only as a fingerprint anywhere in this
 * module.
 */
final readonly class GuestOrderClaimRefused
{
    public function __construct(
        public string $tenantId,
        public string $orderReference,
        public ClaimAttemptOutcome $outcome,
    ) {}
}
