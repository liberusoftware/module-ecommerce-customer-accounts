<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CustomerAccounts\Data\Deadline;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipantRegistration;
use Liberu\Ecommerce\CustomerAccounts\Data\PrivacyRequestDraft;
use Liberu\Ecommerce\CustomerAccounts\Enums\ParticipantOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;
use Liberu\Ecommerce\CustomerAccounts\Events\PrivacyRequestOpened;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ReauthenticationNotRecorded;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Support\CaseProgression;
use Liberu\Ecommerce\CustomerAccounts\Support\ParticipantRegistry;
use Liberu\Ecommerce\CustomerAccounts\Support\Reference;

/**
 * Open the case. **Nothing is erased and nothing is exported here** — this is
 * the write the host never had, and it is the whole point: participants are
 * commissioned afterwards as separate retryable steps, so one failing module
 * loses one step rather than the request.
 *
 * Every registered participant gets its row now, before anybody is asked
 * anything. A case that grew its participant list as answers arrived could never
 * be partial, because the modules that did not answer would never have had a row
 * to be missing from — which is exactly how a synchronous erasure over ten
 * tables reported success about six modules it had never heard of.
 *
 * A participant that does not publish this half of privacy answers `unavailable`
 * immediately rather than being left out. Promotions publishes no export at all;
 * leaving it out of an access case would make that case look complete, and
 * naming it makes the export honestly partial. Four modules in this fleet
 * publish an erasure, six publish nothing, and this is where that shows.
 */
final class OpenPrivacyRequest
{
    public function __construct(
        private readonly ParticipantRegistry $registry = new ParticipantRegistry(),
        private readonly CaseProgression $progression = new CaseProgression(),
    ) {}

    public function __invoke(PrivacyRequestDraft $draft): PrivacyRequest
    {
        // We do not perform the re-authentication and we never see the material.
        // We refuse to open a destructive case that cannot say how the host
        // satisfied itself, because the host's one real control was a password
        // check that left no trace of having happened.
        if ($draft->kind->isDestructive() && ($draft->reauthenticatedVia === null || trim($draft->reauthenticatedVia) === '')) {
            throw ReauthenticationNotRecorded::forErasure($draft->subjectRef);
        }

        $recordedAt = CarbonImmutable::now();
        $deadline = Deadline::in(
            $draft->requestedAt,
            $draft->deadlineDays ?? (int) Config::get('customer-accounts.deadline_days', 30),
            $draft->deadlineTimezone ?? (string) Config::get('customer-accounts.deadline_timezone', 'UTC'),
        );

        $registrations = $this->registry->all();

        $request = DB::transaction(function () use ($draft, $recordedAt, $deadline, $registrations): PrivacyRequest {
            $request = PrivacyRequest::query()->create([
                'reference' => Reference::mint('par'),
                'tenant_id' => $draft->tenantId,
                'subject_ref' => $draft->subjectRef,
                'kind' => $draft->kind,
                'scope' => $draft->scope,
                'lawful_basis' => $draft->lawfulBasis,
                'state' => RequestState::Open,
                'channel' => $draft->channel,
                'requested_by_ref' => $draft->requestedByRef,
                'reason' => $draft->reason,
                'reauthenticated_via' => $draft->reauthenticatedVia,
                'deadline_date' => $deadline->date,
                'deadline_timezone' => $deadline->timezone,
                'requested_at' => $draft->requestedAt,
                'recorded_at' => $recordedAt,
            ]);

            foreach ($registrations as $registration) {
                $publishes = $registration->handles($draft->kind);

                $request->participants()->create([
                    'tenant_id' => $draft->tenantId,
                    'participant' => $registration->name,
                    'label' => $registration->label,
                    'outcome' => $publishes ? ParticipantOutcome::Pending : ParticipantOutcome::Unavailable,
                    'note' => $publishes ? null : "Publishes no {$draft->kind->value}.",
                    'answered_at' => $publishes ? null : $recordedAt,
                ]);
            }

            return $request;
        });

        Event::dispatch(new PrivacyRequestOpened(
            reference: $request->reference,
            tenantId: $request->tenant_id,
            subjectRef: $request->subject_ref,
            kind: $request->kind,
            scope: $request->scope,
            participants: array_map(fn (ParticipantRegistration $r): string => $r->name, $registrations),
        ));

        // A registry in which nothing publishes this half leaves the case
        // already in progress and already unsatisfiable. That is visible on
        // purpose.
        return $this->progression->advance($request);
    }
}
