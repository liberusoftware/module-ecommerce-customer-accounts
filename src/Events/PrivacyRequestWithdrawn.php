<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Events;

final readonly class PrivacyRequestWithdrawn
{
    public function __construct(
        public string $reference,
        public string $tenantId,
        public string $subjectRef,
        public ?string $reason,
    ) {}
}
