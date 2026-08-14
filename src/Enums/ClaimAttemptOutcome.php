<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Enums;

/**
 * Why an attempt ended as it did.
 *
 * `Refused` covers both a reference that does not exist and an address that does
 * not match it, and it covers them *as one outcome* on purpose: the seam answers
 * matched / not matched / unavailable and never says which of the two failed, so
 * neither this module nor any surface above it is able to leak the difference
 * even by accident.
 */
enum ClaimAttemptOutcome: string
{
    case Issued = 'issued';
    case Granted = 'granted';
    case Refused = 'refused';
    case TokenMismatch = 'token_mismatch';
    case Expired = 'expired';
    case RateLimited = 'rate_limited';
    case VerifierUnavailable = 'verifier_unavailable';

    public function isFailure(): bool
    {
        return ! in_array($this, [self::Issued, self::Granted], true);
    }
}
