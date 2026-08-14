<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

use Carbon\CarbonImmutable;
use JsonSerializable;
use Liberu\Ecommerce\CustomerAccounts\Enums\ParticipantOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;

/** One participant's row, as a surface reads it. */
final readonly class ParticipantAnswerView implements JsonSerializable
{
    /** @param array<string, mixed> $summary */
    public function __construct(
        public string $participant,
        public string $label,
        public ParticipantOutcome $outcome,
        public ?RequestScope $scopeApplied,
        public bool $scopeMismatch,
        public array $summary,
        public ?string $note,
        public int $attempts,
        public ?CarbonImmutable $answeredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'participant' => $this->participant,
            'label' => $this->label,
            'outcome' => $this->outcome->value,
            'scope_applied' => $this->scopeApplied?->value,
            'scope_mismatch' => $this->scopeMismatch,
            'summary' => $this->summary,
            'note' => $this->note,
            'attempts' => $this->attempts,
            'answered_at' => $this->answeredAt?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
