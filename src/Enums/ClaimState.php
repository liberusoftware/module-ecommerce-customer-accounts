<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Enums;

/**
 * A claim is evidence with a life, not a boolean.
 *
 * Pending means the reference and the address matched and a token went to the
 * address *on the order*; it entitles the claimant to nothing yet. Granted is
 * the only state that entitles anything, and it is derived from the claim rather
 * than stored as a permission somewhere else.
 */
enum ClaimState: string
{
    case Pending = 'pending';
    case Granted = 'granted';
    case Refused = 'refused';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function entitles(): bool
    {
        return $this === self::Granted;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting proof',
            self::Granted => 'Granted',
            self::Refused => 'Refused',
            self::Expired => 'Expired',
            self::Revoked => 'Revoked',
        };
    }
}
