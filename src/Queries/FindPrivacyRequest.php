<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Queries;

use Liberu\Ecommerce\CustomerAccounts\Data\ParticipantAnswerView;
use Liberu\Ecommerce\CustomerAccounts\Data\PrivacyRequestView;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequestParticipant;

/**
 * The case as a surface reads it — **with every participant on it**, including
 * the ones that answered nothing.
 *
 * A view that returned only the answers would be a view in which a silent module
 * is indistinguishable from a module that was never registered, and that
 * distinction is the module.
 */
final class FindPrivacyRequest
{
    public function __invoke(string $reference): ?PrivacyRequestView
    {
        $request = PrivacyRequest::query()->where('reference', $reference)->first();

        if ($request === null) {
            return null;
        }

        $participants = $request->participants()
            ->orderBy('participant')
            ->get()
            ->map(fn (PrivacyRequestParticipant $p): ParticipantAnswerView => new ParticipantAnswerView(
                participant: $p->participant,
                label: $p->label,
                outcome: $p->outcome,
                scopeApplied: $p->scope_applied,
                scopeMismatch: $p->scope_mismatch,
                summary: $p->summary ?? [],
                note: $p->note,
                attempts: $p->attempts,
                answeredAt: $p->answered_at,
            ))
            ->values()
            ->all();

        return new PrivacyRequestView(
            reference: $request->reference,
            tenantId: $request->tenant_id,
            subjectRef: $request->subject_ref,
            kind: $request->kind,
            scope: $request->scope,
            lawfulBasis: $request->lawful_basis,
            state: $request->state,
            deadline: $request->deadline(),
            requestedAt: $request->requested_at,
            recordedAt: $request->recorded_at,
            concludedAt: $request->concluded_at,
            reauthenticatedVia: $request->reauthenticated_via,
            participants: $participants,
            progress: $request->progress(),
        );
    }
}
