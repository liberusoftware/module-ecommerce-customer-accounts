<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CustomerAccounts\Contracts\VerifiesGuestOrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Data\ClaimProof;
use Liberu\Ecommerce\CustomerAccounts\Enums\Channel;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimAttemptOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimState;
use Liberu\Ecommerce\CustomerAccounts\Events\GuestOrderClaimOpened;
use Liberu\Ecommerce\CustomerAccounts\Events\GuestOrderClaimRefused;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ClaimRefused;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ClaimVerificationUnavailable;
use Liberu\Ecommerce\CustomerAccounts\Models\OrderClaim;
use Liberu\Ecommerce\CustomerAccounts\Support\ClaimAttempts;
use Liberu\Ecommerce\CustomerAccounts\Support\Fingerprint;
use Liberu\Ecommerce\CustomerAccounts\Support\Reference;

/**
 * Start a guest order claim: proof of possession, not a lookup.
 *
 * A reference plus the address it was placed with is **not sufficient on its
 * own**. Both appear on an emailed receipt, and a receipt gets forwarded — to a
 * partner, to an accountant, to a mailing list somebody was careless with. So
 * the pair only opens the claim; what completes it is a token sent to the
 * address on the order, which is possession of the mailbox rather than knowledge
 * of two strings.
 *
 * This module publishes the lifecycle and **not the mail**. It has no
 * notification, no mailable and no address book; the host listens for
 * GuestOrderClaimOpened and sends the proof it was handed.
 *
 * Every exit records an attempt, including the refusals — especially the
 * refusals. Forty failures in an hour is the only signal there is that a claim
 * form is being used as an order-reference oracle, and a module that stores only
 * successful claims cannot show an operator that it happened.
 */
final class OpenGuestOrderClaim
{
    public function __construct(
        private readonly ClaimAttempts $attempts = new ClaimAttempts(),
    ) {}

    public function __invoke(
        string $tenantId,
        string $orderReference,
        string $email,
        string $claimantRef,
        Channel $channel = Channel::Web,
        ?CarbonImmutable $requestedAt = null,
    ): ClaimProof {
        $now = CarbonImmutable::now();
        $fingerprint = Fingerprint::of($email);

        // The limit is here rather than in one surface's middleware, so every
        // surface inherits it and none of them can forget it.
        $this->attempts->assertWithinLimit($tenantId, $orderReference, $fingerprint, $now, function () use ($tenantId, $orderReference, $fingerprint, $channel, $now): void {
            $this->attempts->record($tenantId, $orderReference, $fingerprint, null, ClaimAttemptOutcome::RateLimited, $channel, $now);
        });

        $verifier = App::bound(VerifiesGuestOrderClaim::class)
            ? App::make(VerifiesGuestOrderClaim::class)
            : null;

        // Unbound closes claiming rather than opening it. Accepting the claim
        // and verifying later would grant entitlement on an unchecked assertion,
        // which is the one thing this seam exists to prevent.
        if (! $verifier instanceof VerifiesGuestOrderClaim) {
            $this->attempts->record($tenantId, $orderReference, $fingerprint, null, ClaimAttemptOutcome::VerifierUnavailable, $channel, $now);

            throw ClaimVerificationUnavailable::forTenant($tenantId);
        }

        $verification = $verifier->verifyClaim($tenantId, $orderReference, $email);

        if (! $verification->available) {
            $this->attempts->record($tenantId, $orderReference, $fingerprint, null, ClaimAttemptOutcome::VerifierUnavailable, $channel, $now);

            throw ClaimVerificationUnavailable::forTenant($tenantId);
        }

        if (! $verification->matched) {
            $this->attempts->record($tenantId, $orderReference, $fingerprint, null, ClaimAttemptOutcome::Refused, $channel, $now);

            Event::dispatch(new GuestOrderClaimRefused($tenantId, $orderReference, ClaimAttemptOutcome::Refused));

            // One refusal for "no such order" and "wrong address" both. We were
            // never told which, so there is nothing here to leak.
            throw ClaimRefused::evidenceDoesNotMatch();
        }

        $token = Reference::secret();
        $ttl = (int) Config::get('customer-accounts.claim.token_ttl_minutes', 60);

        $claim = OrderClaim::query()->create([
            'reference' => Reference::mint('clm'),
            'tenant_id' => $tenantId,
            'order_reference' => $orderReference,
            'claimant_ref' => $claimantRef,
            'email_fingerprint' => $fingerprint,
            'state' => ClaimState::Pending,
            'channel' => $channel,
            'token_fingerprint' => Fingerprint::ofSecret($token),
            'expires_at' => $now->addMinutes($ttl),
            'requested_at' => $requestedAt ?? $now,
            'recorded_at' => $now,
        ]);

        $this->attempts->record($tenantId, $orderReference, $fingerprint, $claim->reference, ClaimAttemptOutcome::Issued, $channel, $now);

        Event::dispatch(new GuestOrderClaimOpened(
            claimReference: $claim->reference,
            tenantId: $tenantId,
            orderReference: $orderReference,
            claimantRef: $claimantRef,
        ));

        return new ClaimProof($claim->reference, $token, $claim->expires_at ?? $now->addMinutes($ttl));
    }
}
