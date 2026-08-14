<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Events;

use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;

/**
 * This module erased its **own** rows — the lists, the shares and the subject
 * reference on its claims.
 *
 * It is not a participant of its own registry. A coordinator that registered
 * itself would be able to report its own erasure as one more green tick, and the
 * failure mode of that is the case completing while the coordinator's rows
 * survive because somebody removed the entry.
 */
final readonly class SubjectRecordErased
{
    public function __construct(
        public string $subjectRef,
        public RequestScope $scope,
        public ?string $tenantId,
        public int $listsDeleted,
        public int $sharesRevoked,
        public int $claimsRedacted,
    ) {}
}
