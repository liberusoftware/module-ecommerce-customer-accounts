<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Events;

use Liberu\Ecommerce\CustomerAccounts\Enums\ParticipantOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;

final readonly class ParticipantAnswered
{
    public function __construct(
        public string $requestReference,
        public string $tenantId,
        public string $participant,
        public ParticipantOutcome $outcome,
        public ?RequestScope $scopeApplied,
        public bool $scopeMismatch,
    ) {}
}
