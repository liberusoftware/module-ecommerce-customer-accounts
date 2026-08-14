<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

use Carbon\CarbonImmutable;
use JsonSerializable;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;

/**
 * What a participant is asked. Everything an adapter needs and nothing it does
 * not: no model, no query, no callback back into this module.
 *
 * `tenantId` is always present — it is the merchant the case was raised at — and
 * `scope` says whether the answer should stop there. A cross-tenant participant
 * ignores the tenant and says so in its answer; a per-tenant one uses it and
 * says so. Neither has to guess, and the mismatch between what was asked and
 * what was applied is recorded rather than reconciled away.
 */
final readonly class ParticipationRequest implements JsonSerializable
{
    public function __construct(
        public string $requestReference,
        public string $tenantId,
        public string $subjectRef,
        public RequestKind $kind,
        public RequestScope $scope,
        public ?string $actorRef,
        public ?string $reason,
        public CarbonImmutable $requestedAt,
    ) {}

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'request_reference' => $this->requestReference,
            'tenant_id' => $this->tenantId,
            'subject_ref' => $this->subjectRef,
            'kind' => $this->kind->value,
            'scope' => $this->scope->value,
            'actor_ref' => $this->actorRef,
            'reason' => $this->reason,
            'requested_at' => $this->requestedAt->toIso8601String(),
        ];
    }

    /** @return array<string, string|null> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
