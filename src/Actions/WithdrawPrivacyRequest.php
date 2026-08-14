<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;
use Liberu\Ecommerce\CustomerAccounts\Events\PrivacyRequestWithdrawn;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\PrivacyRequestNotFound;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\RequestAlreadyConcluded;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;

/**
 * The subject changed their mind.
 *
 * Withdrawal does not undo anything a participant already did — an erasure that
 * ran is not coming back — and it does not pretend to. It stops further
 * participants being commissioned and it records that the person stopped asking,
 * which is a different fact from a case that failed.
 */
final class WithdrawPrivacyRequest
{
    public function __invoke(string $requestReference, ?string $reason = null): PrivacyRequest
    {
        $request = PrivacyRequest::query()->where('reference', $requestReference)->first();

        if ($request === null) {
            throw PrivacyRequestNotFound::referenced($requestReference);
        }

        if ($request->state->isConcluded()) {
            throw RequestAlreadyConcluded::in($request->reference, $request->state);
        }

        $request->forceFill([
            'state' => RequestState::Withdrawn,
            'concluded_at' => CarbonImmutable::now(),
        ])->save();

        Event::dispatch(new PrivacyRequestWithdrawn(
            reference: $request->reference,
            tenantId: $request->tenant_id,
            subjectRef: $request->subject_ref,
            reason: $reason,
        ));

        return $request;
    }
}
