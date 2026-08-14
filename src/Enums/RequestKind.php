<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Enums;

/** What the person asked for. Access is Art. 15; erasure is Art. 17. */
enum RequestKind: string
{
    case Access = 'access';
    case Erasure = 'erasure';

    public function label(): string
    {
        return match ($this) {
            self::Access => 'Subject access',
            self::Erasure => 'Erasure',
        };
    }

    /** An erasure destroys; the host must have re-authenticated the person first. */
    public function isDestructive(): bool
    {
        return $this === self::Erasure;
    }
}
