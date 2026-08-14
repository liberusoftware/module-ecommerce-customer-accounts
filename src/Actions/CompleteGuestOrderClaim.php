<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimAttemptOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimState;
use Liberu\Ecommerce\CustomerAccounts\Events\GuestOrderClaimGranted;
use Liberu\Ecommerce\CustomerAccounts\Events\GuestOrderClaimRefused;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ClaimNotClaimable;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ClaimRefused;
use Liberu\Ecommerce\CustomerAccounts\Models\OrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Support\ClaimAttempts;
use Liberu\Ecommerce\CustomerAccounts\Support\Fingerprint;

/**
 * Present the proof and be entitled — or not, and be recorded either way.
 *
 * The token is compared with `hash_equals` against a stored fingerprint, so
 * neither the database nor the response time answers a question about it. On
 * success the fingerprint is cleared: the proof was for one grant and there is
 * no reason for a usable secret to survive its use.
 *
 * Rate-limited on the same counter as opening. A token is 32 random bytes and
 * guessing one is not the threat; the threat is the same claim form being used
 * to hammer a merchant, and one limit over both steps is what actually bounds
 * that.
 */
final class CompleteGuestOrderClaim
{
    public function __construct(
        private readonly ClaimAttempts $attempts = new ClaimAttempts(),
    ) {}

    public function __invoke(string $claimReference, string $token): OrderClaim
    {
        $claim = OrderClaim::query()->where('reference', $claimReference)->first();

        if ($claim === null) {
            throw ClaimNotClaimable::notFound($claimReference);
        }

        $now = CarbonImmutable::now();
        $fingerprint = (string) $claim->email_fingerprint;

        $this->attempts->assertWithinLimit($claim->tenant_id, $claim->order_reference, $fingerprint, $now, function () use ($claim, $fingerprint, $now): void {
            $this->attempts->record($claim->tenant_id, $claim->order_reference, $fingerprint, $claim->reference, ClaimAttemptOutcome::RateLimited, $claim->channel, $now);
        });

        if ($claim->state !== ClaimState::Pending) {
            throw ClaimNotClaimable::in($claim->reference, $claim->state);
        }

        if ($claim->hasExpired($now)) {
            $claim->forceFill(['state' => ClaimState::Expired, 'closed_at' => $now, 'token_fingerprint' => null])->save();
            $this->attempts->record($claim->tenant_id, $claim->order_reference, $fingerprint, $claim->reference, ClaimAttemptOutcome::Expired, $claim->channel, $now);

            throw ClaimNotClaimable::expired($claim->reference);
        }

        if ($claim->token_fingerprint === null || ! Fingerprint::matches($claim->token_fingerprint, $token)) {
            $this->attempts->record($claim->tenant_id, $claim->order_reference, $fingerprint, $claim->reference, ClaimAttemptOutcome::TokenMismatch, $claim->channel, $now);

            Event::dispatch(new GuestOrderClaimRefused($claim->tenant_id, $claim->order_reference, ClaimAttemptOutcome::TokenMismatch));

            throw ClaimRefused::proofDoesNotMatch();
        }

        $claim->forceFill([
            'state' => ClaimState::Granted,
            'granted_at' => $now,
            'token_fingerprint' => null,
        ])->save();

        $this->attempts->record($claim->tenant_id, $claim->order_reference, $fingerprint, $claim->reference, ClaimAttemptOutcome::Granted, $claim->channel, $now);

        Event::dispatch(new GuestOrderClaimGranted(
            claimReference: $claim->reference,
            tenantId: $claim->tenant_id,
            orderReference: $claim->order_reference,
            claimantRef: (string) $claim->claimant_ref,
        ));

        return $claim;
    }
}
