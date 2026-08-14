<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

use Carbon\CarbonImmutable;

/**
 * The one and only time the claim token exists in readable form.
 *
 * The host mails it **to the address on the order** — which is the whole
 * proof-of-possession, since the reference and the address both appear on a
 * receipt that may have been forwarded. This module publishes the lifecycle and
 * not the mail: it has no notification, no mailable and no address book, and the
 * address it verified against was never returned to it.
 *
 * Only a fingerprint is stored. A token that can be read out of the database is
 * a token an operator can use.
 */
final readonly class ClaimProof
{
    public function __construct(
        public string $claimReference,
        public string $token,
        public CarbonImmutable $expiresAt,
    ) {}
}
