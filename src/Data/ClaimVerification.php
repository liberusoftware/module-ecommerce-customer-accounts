<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

/**
 * Matched, not matched, or we cannot say.
 *
 * There is deliberately no reason on a "not matched": the seam is not permitted
 * to tell us whether the reference was unknown or the address was wrong, because
 * anything we know, a surface can eventually leak. One value, one message, one
 * response time.
 */
final readonly class ClaimVerification
{
    private function __construct(
        public bool $available,
        public bool $matched,
    ) {}

    public static function matched(): self
    {
        return new self(true, true);
    }

    public static function notMatched(): self
    {
        return new self(true, false);
    }

    public static function unavailable(): self
    {
        return new self(false, false);
    }
}
