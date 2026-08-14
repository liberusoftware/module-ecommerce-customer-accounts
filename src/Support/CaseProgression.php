<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CustomerAccounts\Actions\EraseSubjectRecord;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;
use Liberu\Ecommerce\CustomerAccounts\Events\PrivacyRequestConcluded;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;

/**
 * The one place a case changes state, and the one place `Completed` can be
 * written.
 *
 * Completed requires **every** registered participant to have completed. Not
 * most of them, not the ones that were reachable, not "no errors" — every one,
 * counted from the rows. Everything else that is finished is Partial, and
 * Partial carries the outstanding names with it so a listener that wants to
 * notify somebody cannot write "your data has been erased" without having been
 * handed the list of who did not answer.
 *
 * A case with no registered participants can never be satisfied. That is not an
 * edge case to smooth over: an empty registry means nothing was asked, and a
 * request that asked nobody anything must not read as done.
 */
final class CaseProgression
{
    /** Recompute after an answer. Concludes only when every participant completed. */
    public function advance(PrivacyRequest $request): PrivacyRequest
    {
        if ($request->state->isConcluded()) {
            return $request;
        }

        $progress = $request->progress();

        if ($progress->isSatisfied()) {
            return $this->conclude($request, RequestState::Completed);
        }

        $state = $progress->completed + $progress->unavailable + $progress->failed > 0
            ? RequestState::InProgress
            : RequestState::Open;

        if ($request->state !== $state) {
            $request->forceFill(['state' => $state])->save();
        }

        return $request;
    }

    /**
     * Finish the case, whatever state it is in.
     *
     * Concluding an erasure clears **this module's own** rows and revokes the
     * subject's shares — explicitly, because the host forgot to, and a share
     * token published to third parties that still resolves after an erasure is
     * the failure that outlives the request. It happens on a partial conclusion
     * too: our own rows are ours to clear whatever the other participants did.
     */
    public function conclude(PrivacyRequest $request, RequestState $state): PrivacyRequest
    {
        $request->forceFill([
            'state' => $state,
            'concluded_at' => CarbonImmutable::now(),
        ])->save();

        if ($request->kind === RequestKind::Erasure) {
            (new EraseSubjectRecord())(
                $request->subject_ref,
                $request->scope,
                $request->scope === RequestScope::Tenant ? $request->tenant_id : null,
            );
        }

        Event::dispatch(new PrivacyRequestConcluded(
            reference: $request->reference,
            tenantId: $request->tenant_id,
            subjectRef: $request->subject_ref,
            state: $state,
            progress: $request->progress(),
        ));

        return $request;
    }
}
