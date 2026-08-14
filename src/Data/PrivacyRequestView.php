<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

use Carbon\CarbonImmutable;
use JsonSerializable;
use Liberu\Ecommerce\CustomerAccounts\Enums\LawfulBasis;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;

/** The case as a surface reads it, with every participant's answer including the silent ones. */
final readonly class PrivacyRequestView implements JsonSerializable
{
    /** @param list<ParticipantAnswerView> $participants */
    public function __construct(
        public string $reference,
        public string $tenantId,
        public string $subjectRef,
        public RequestKind $kind,
        public RequestScope $scope,
        public LawfulBasis $lawfulBasis,
        public RequestState $state,
        public Deadline $deadline,
        public CarbonImmutable $requestedAt,
        public CarbonImmutable $recordedAt,
        public ?CarbonImmutable $concludedAt,
        public ?string $reauthenticatedVia,
        public array $participants,
        public RequestProgress $progress,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'tenant_id' => $this->tenantId,
            'subject_ref' => $this->subjectRef,
            'kind' => $this->kind->value,
            'scope' => $this->scope->value,
            'lawful_basis' => $this->lawfulBasis->value,
            'state' => $this->state->value,
            'deadline' => $this->deadline->toArray(),
            'requested_at' => $this->requestedAt->toIso8601String(),
            'recorded_at' => $this->recordedAt->toIso8601String(),
            'concluded_at' => $this->concludedAt?->toIso8601String(),
            'reauthenticated_via' => $this->reauthenticatedVia,
            'participants' => array_map(fn (ParticipantAnswerView $p): array => $p->toArray(), $this->participants),
            'progress' => $this->progress->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
