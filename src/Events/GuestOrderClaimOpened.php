<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Events;

/**
 * A claim is awaiting its proof. This is the event a host listens for in order
 * to mail the token **to the address on the order** — the token itself is not on
 * the event, because an event is broadcast, logged and serialised, and a secret
 * on one is a secret in the log.
 */
final readonly class GuestOrderClaimOpened
{
    public function __construct(
        public string $claimReference,
        public string $tenantId,
        public string $orderReference,
        public string $claimantRef,
    ) {}
}
