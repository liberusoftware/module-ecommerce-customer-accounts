<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

/**
 * A share's token, returned once at minting and never readable again.
 *
 * The share belongs to the **list**, not to the person. The host put one token
 * on `users` with a unique index, which is why one shared link showed a
 * different set of items depending on which storefront the visitor landed on —
 * silently, with nothing on the page indicating anything was missing. One token,
 * many answers.
 */
final readonly class ShareProof
{
    public function __construct(
        public string $shareReference,
        public string $token,
    ) {}
}
