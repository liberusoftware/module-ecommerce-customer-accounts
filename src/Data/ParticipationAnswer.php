<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Data;

use Liberu\Ecommerce\CustomerAccounts\Enums\ParticipantOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;

/**
 * What one participant did, in its own words.
 *
 * `scopeApplied` is mandatory on a completed answer and absent otherwise, which
 * is the shape the fleet forces: Commerce Customers can only answer everywhere
 * and Reviews can only answer per tenant, so an answer that does not say which
 * it applied cannot be checked against what the case asked for. A participant
 * cannot lie usefully here — it is stating a fact about its own implementation.
 *
 * `summary` is counts, for the operator. `payload` is the export contribution,
 * and it is null for an erasure. Neither is interpreted by this module: we hold
 * what a participant said, we do not restate it.
 */
final readonly class ParticipationAnswer
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $payload
     */
    private function __construct(
        public ParticipantOutcome $outcome,
        public ?RequestScope $scopeApplied,
        public array $summary,
        public ?array $payload,
        public ?string $note,
    ) {}

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $payload
     */
    public static function completed(RequestScope $scopeApplied, array $summary = [], ?array $payload = null, ?string $note = null): self
    {
        return new self(ParticipantOutcome::Completed, $scopeApplied, $summary, $payload, $note);
    }

    /**
     * Nothing was attempted: nothing is bound, or the module does not publish
     * this half. The case goes partial and this participant is named.
     */
    public static function unavailable(string $note): self
    {
        return new self(ParticipantOutcome::Unavailable, null, [], null, $note);
    }

    /** Something was attempted and did not work. Retryable, and never silent. */
    public static function failed(string $note): self
    {
        return new self(ParticipantOutcome::Failed, null, [], null, $note);
    }
}
