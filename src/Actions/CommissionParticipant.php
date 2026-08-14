<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationAnswer;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationRequest;
use Liberu\Ecommerce\CustomerAccounts\Enums\ParticipantOutcome;
use Liberu\Ecommerce\CustomerAccounts\Events\ParticipantAnswered;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ParticipantAlreadyCompleted;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ParticipantNotRegistered;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\PrivacyRequestNotFound;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\RequestAlreadyConcluded;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequestParticipant;
use Liberu\Ecommerce\CustomerAccounts\Support\CaseProgression;
use Liberu\Ecommerce\CustomerAccounts\Support\ParticipantRegistry;
use Throwable;

/**
 * Ask one participant to answer for its own rows, and record what it said.
 *
 * One participant, one step, retryable. The host did all ten tables in one
 * transaction inside an HTTP request, so a single failure rolled back the parts
 * it owned, returned a 500, and left the person with nothing to quote at anyone.
 *
 * **This never calls another module's action.** It calls the one contract this
 * package publishes; the host maps it onto `RedactPerson`, `EraseAuthor`,
 * `RedactCustomerFromRedemptions` or whatever a module publishes. Four verbs,
 * four return types and three names for the same subject stay in the adapters,
 * where they are somebody's explicit decision, rather than here, where they
 * would be four sibling dependencies this package must install without.
 *
 * Every path out of here writes an answer. A participant that throws is
 * recorded as Failed and retried later; it is not allowed to become an exception
 * escaping into a request that then has no record of having asked.
 */
final class CommissionParticipant
{
    public function __construct(
        private readonly ParticipantRegistry $registry = new ParticipantRegistry(),
        private readonly CaseProgression $progression = new CaseProgression(),
    ) {}

    public function __invoke(string $requestReference, string $participantName): PrivacyRequestParticipant
    {
        $request = PrivacyRequest::query()->where('reference', $requestReference)->first();

        if ($request === null) {
            throw PrivacyRequestNotFound::referenced($requestReference);
        }

        if ($request->state->isConcluded()) {
            throw RequestAlreadyConcluded::in($request->reference, $request->state);
        }

        $participant = $request->participants()->where('participant', $participantName)->first();

        if (! $participant instanceof PrivacyRequestParticipant) {
            throw ParticipantNotRegistered::named($participantName);
        }

        if ($participant->outcome === ParticipantOutcome::Completed) {
            throw ParticipantAlreadyCompleted::for($request->reference, $participantName);
        }

        $answer = $this->ask($request, $participantName);

        // A mismatch is what the fleet's two scopes actually feel like. Commerce
        // Customers can only answer everywhere and Reviews only per tenant, so
        // one of them is always going to have applied something other than what
        // was asked for. Recorded, never reconciled: an erasure that reached
        // further than the case asked and one that reached less far are both
        // facts an operator has to be able to see.
        $mismatch = $answer->scopeApplied !== null && $answer->scopeApplied !== $request->scope;

        $participant->forceFill([
            'outcome' => $answer->outcome,
            'scope_applied' => $answer->scopeApplied,
            'scope_mismatch' => $mismatch,
            'summary' => $answer->summary === [] ? null : $answer->summary,
            'payload' => $answer->payload,
            'note' => $answer->note,
            'attempts' => $participant->attempts + 1,
            'answered_at' => CarbonImmutable::now(),
        ])->save();

        Event::dispatch(new ParticipantAnswered(
            requestReference: $request->reference,
            tenantId: $request->tenant_id,
            participant: $participantName,
            outcome: $answer->outcome,
            scopeApplied: $answer->scopeApplied,
            scopeMismatch: $mismatch,
        ));

        $this->progression->advance($request);

        return $participant->refresh();
    }

    private function ask(PrivacyRequest $request, string $participantName): ParticipationAnswer
    {
        try {
            $registration = $this->registry->get($participantName);
        } catch (ParticipantNotRegistered) {
            // The row outlives the config entry. A participant taken out of the
            // registry mid-case is unavailable rather than absent, so the case
            // still cannot be called done.
            return ParticipationAnswer::unavailable('No longer registered.');
        }

        if (! $registration->handles($request->kind)) {
            return ParticipationAnswer::unavailable("Publishes no {$request->kind->value}.");
        }

        $adapter = $this->registry->resolve($participantName);

        if ($adapter === null) {
            return ParticipationAnswer::unavailable('Registered, but nothing is bound to answer for it.');
        }

        $payload = new ParticipationRequest(
            requestReference: $request->reference,
            tenantId: $request->tenant_id,
            subjectRef: $request->subject_ref,
            kind: $request->kind,
            scope: $request->scope,
            actorRef: $request->requested_by_ref,
            reason: $request->reason,
            requestedAt: $request->requested_at,
        );

        try {
            return $adapter->participate($payload);
        } catch (Throwable $e) {
            return ParticipationAnswer::failed($e::class.': '.$e->getMessage());
        }
    }
}
