<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\CustomerAccounts\Enums\Channel;
use Liberu\Ecommerce\CustomerAccounts\Enums\LawfulBasis;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;

/**
 * What somebody has to say to open a case.
 *
 * Two times, both required, because they answer different questions. The
 * regulatory clock runs from `requestedAt` — the moment the person asked, which
 * may be a letter dated last Tuesday — and the support conversation runs from
 * the moment it was recorded, which this module stamps itself.
 *
 * `reauthenticatedVia` names *how* the host satisfied itself the request is from
 * the subject — "password", "re-login", "identity documents at the desk". It is
 * a name, never material: no password, no token and no second factor is read or
 * written by this module. It is required for an erasure and optional for an
 * access request, because one of those is irreversible.
 */
final readonly class PrivacyRequestDraft
{
    public function __construct(
        public string $tenantId,
        public string $subjectRef,
        public RequestKind $kind,
        public RequestScope $scope,
        public LawfulBasis $lawfulBasis,
        public CarbonImmutable $requestedAt,
        public ?string $requestedByRef = null,
        public Channel $channel = Channel::Web,
        public ?string $reason = null,
        public ?string $reauthenticatedVia = null,
        public ?int $deadlineDays = null,
        public ?string $deadlineTimezone = null,
    ) {}
}
