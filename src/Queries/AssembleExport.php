<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Queries;

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\CustomerAccounts\Data\ExportArtefact;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\PrivacyRequestNotFound;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\WrongRequestKind;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequestParticipant;

/**
 * Assemble the answer to an access request from what participants contributed.
 *
 * Assembled, never streamed. The host's exporter reached into ten tables inside
 * a controller and returned a JSON download — which is why its idea of
 * "everything" drifted from the erasure's in both directions at once: segment
 * memberships were exported and never erased, the wishlist was erased and never
 * exported, and the behavioural profile in `customer_metrics` — lifetime value,
 * predicted next order, retention score — was neither. Nobody wrote that; two
 * whitelists in two files diverged.
 *
 * Here there is one list, it is the participant list, and a participant that did
 * not contribute is named in `missing` rather than being absent from a payload
 * nobody counted.
 */
final class AssembleExport
{
    public function __invoke(string $requestReference): ExportArtefact
    {
        $request = PrivacyRequest::query()->where('reference', $requestReference)->first();

        if ($request === null) {
            throw PrivacyRequestNotFound::referenced($requestReference);
        }

        if ($request->kind !== RequestKind::Access) {
            throw WrongRequestKind::expected($request->reference, RequestKind::Access, $request->kind);
        }

        $contributions = [];
        $missing = [];

        foreach ($request->participants()->orderBy('participant')->get() as $participant) {
            if (! $participant instanceof PrivacyRequestParticipant) {
                continue;
            }

            if ($participant->outcome->isSatisfied() && $participant->payload !== null) {
                $contributions[$participant->participant] = $participant->payload;

                continue;
            }

            $missing[] = $participant->participant;
        }

        return new ExportArtefact(
            requestReference: $request->reference,
            subjectRef: $request->subject_ref,
            assembledAt: CarbonImmutable::now(),
            // Complete means every registered participant contributed. A case
            // with no participants at all is not complete either — nothing was
            // asked, so nothing may be presented as everything.
            complete: $missing === [] && $contributions !== [],
            contributions: $contributions,
            missing: $missing,
        );
    }
}
