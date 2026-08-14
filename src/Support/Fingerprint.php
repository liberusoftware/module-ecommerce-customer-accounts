<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Support;

/**
 * A one-way fingerprint, used for two different reasons that happen to want the
 * same function.
 *
 * For a **secret** (a claim token) it is the ordinary reason: what is stored
 * must not be usable.
 *
 * For an **address** it is the less obvious one. A failed claim attempt is a
 * record of somebody typing an email address at us, and the address they typed
 * may be a third party's — recording forty failed attempts in plaintext builds
 * exactly the personal-data pile this module exists to shrink. The fingerprint
 * still counts, still groups, and still shows an operator that somebody tried
 * forty times, which is all the security signal needs.
 *
 * Comparison is `hash_equals`, so a secret check is not a timing oracle.
 */
final class Fingerprint
{
    public static function of(string $value): string
    {
        return hash('sha256', mb_strtolower(trim($value)));
    }

    /** A secret is fingerprinted verbatim: case and whitespace are part of it. */
    public static function ofSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public static function matches(string $fingerprint, string $candidate): bool
    {
        return hash_equals($fingerprint, self::ofSecret($candidate));
    }
}
