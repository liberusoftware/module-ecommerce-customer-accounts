<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Exceptions;

/**
 * One refusal, one message, for both "no such order" and "wrong address".
 *
 * The message names neither, and the seam we asked never told us which it was,
 * so there is nothing here for a surface to decode even if somebody later
 * decides to be helpful. Two conditions rendered from one exception by reading
 * its message is a recorded fleet defect; two conditions that are genuinely
 * indistinguishable at the source is the fix.
 */
final class ClaimRefused extends CustomerAccountsException
{
    public static function evidenceDoesNotMatch(): self
    {
        return new self('The order reference and email address presented do not match an order at this merchant.');
    }

    public static function proofDoesNotMatch(): self
    {
        return new self('The proof presented does not match this claim.');
    }
}
