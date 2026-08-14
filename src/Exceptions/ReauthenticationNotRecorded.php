<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Exceptions;

/**
 * An erasure without a recorded re-authentication is refused.
 *
 * The host's one control was a password check, and it was the right control —
 * the defect was that nothing recorded it happened. We do not perform the check
 * (no password, no token and no second factor is read here) and we do not accept
 * a destructive case that cannot say how the host satisfied itself.
 */
final class ReauthenticationNotRecorded extends CustomerAccountsException
{
    public static function forErasure(string $subjectRef): self
    {
        return new self("An erasure request for [{$subjectRef}] must record how the subject was re-authenticated.");
    }
}
