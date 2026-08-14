<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Actions;

use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\PrivacyRequestNotFound;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\RequestAlreadyConcluded;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Support\CaseProgression;

/**
 * Finish a case that is not going to get any better — the deadline arrived, or
 * an operator decided to stop retrying a module that is never coming back.
 *
 * It cannot force a case to Completed. If every participant completed, the case
 * concluded itself when the last one answered and this is a no-op; if any did
 * not, the answer is Partial and the outstanding participants are named. There
 * is no operator override, because an override is the button somebody presses to
 * make a report look finished.
 */
final class ConcludePrivacyRequest
{
    public function __construct(
        private readonly CaseProgression $progression = new CaseProgression(),
    ) {}

    public function __invoke(string $requestReference): PrivacyRequest
    {
        $request = PrivacyRequest::query()->where('reference', $requestReference)->first();

        if ($request === null) {
            throw PrivacyRequestNotFound::referenced($requestReference);
        }

        if ($request->state->isConcluded()) {
            throw RequestAlreadyConcluded::in($request->reference, $request->state);
        }

        return $this->progression->conclude(
            $request,
            $request->progress()->isSatisfied() ? RequestState::Completed : RequestState::Partial,
        );
    }
}
