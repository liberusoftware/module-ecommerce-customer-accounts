<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Support;

/**
 * Opaque, unguessable public identifiers.
 *
 * `random_bytes` rather than `Str::ulid()`: ULIDs need symfony/uid, which
 * `illuminate/support` does not require — only `laravel/framework` does — so a
 * package that declares support and reaches for a ULID passes its own CI on the
 * testbench's framework and breaks for the first consumer that does not have it.
 * A reference here is also deliberately *not* sortable: a sortable public id
 * tells a holder how many cases were opened before theirs.
 */
final class Reference
{
    public static function mint(string $prefix): string
    {
        return $prefix.'_'.bin2hex(random_bytes(12));
    }

    /**
     * A claim's proof-of-possession token. Longer, because it is a secret rather
     * than a name, and it is never stored — only its fingerprint is.
     */
    public static function secret(): string
    {
        return bin2hex(random_bytes(32));
    }
}
