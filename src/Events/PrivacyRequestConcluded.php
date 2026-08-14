<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Events;

use Liberu\Ecommerce\CustomerAccounts\Data\RequestProgress;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;

/**
 * Carries the progress with it, so a listener that wants to notify somebody has
 * the outstanding participants in hand and cannot write "your data has been
 * erased" without having been given the list of who did not answer.
 */
final readonly class PrivacyRequestConcluded
{
    public function __construct(
        public string $reference,
        public string $tenantId,
        public string $subjectRef,
        public RequestState $state,
        public RequestProgress $progress,
    ) {}
}
