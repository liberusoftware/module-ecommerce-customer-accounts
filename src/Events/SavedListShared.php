<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Events;

final readonly class SavedListShared
{
    public function __construct(
        public string $shareReference,
        public string $tenantId,
        public string $listReference,
        public string $ownerRef,
    ) {}
}
