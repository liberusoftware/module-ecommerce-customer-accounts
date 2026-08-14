<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Enums;

/** Where a request or a claim arrived from. Part of the evidence, not decoration. */
enum Channel: string
{
    case Web = 'web';
    case Api = 'api';
    case Email = 'email';
    case Phone = 'phone';
    case Post = 'post';
    case Desk = 'desk';
}
