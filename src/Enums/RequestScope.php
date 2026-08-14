<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Enums;

/**
 * How far the request reaches — and it is a field rather than a convention
 * because the fleet genuinely disagrees.
 *
 * Commerce Customers erases a person from every merchant file it holds, because
 * a person is a person. Attribution and Analytics erases within one merchant,
 * because a global erasure there would tell merchant A that merchant B exists.
 * Both are right on their own terms, so "erase me" already means two things, and
 * the only honest way to sit above that is to make the request say which it
 * meant and make every answer say which it applied.
 */
enum RequestScope: string
{
    /** This merchant only. */
    case Tenant = 'tenant';

    /** The person, wherever this deployment holds them. */
    case Everywhere = 'everywhere';

    public function label(): string
    {
        return match ($this) {
            self::Tenant => 'This merchant',
            self::Everywhere => 'Everywhere in this deployment',
        };
    }
}
