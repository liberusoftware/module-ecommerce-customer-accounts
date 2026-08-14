<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\CustomerAccounts\Enums\Channel;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimAttemptOutcome;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ClaimRateLimited;
use Liberu\Ecommerce\CustomerAccounts\Models\ClaimAttempt;

/**
 * The attempt log and the limit computed from it — one mechanism, because they
 * are the same fact read twice.
 *
 * Counted **two ways** and limited on the worse of them: per order reference,
 * which bounds hammering one order, and per address fingerprint, which bounds
 * one person walking a list of references. Either alone is trivially sidestepped
 * by varying the other.
 *
 * Counting from rows rather than from a cache is the deliberate choice. A cache
 * counter is lost on a restart and, more to the point, is invisible: the whole
 * reason to record failed attempts is so an operator can see them, and a limit
 * enforced somewhere unreadable is a limit nobody can audit.
 */
final class ClaimAttempts
{
    public function record(
        string $tenantId,
        string $orderReference,
        string $emailFingerprint,
        ?string $claimReference,
        ClaimAttemptOutcome $outcome,
        Channel $channel,
        CarbonImmutable $at,
    ): ClaimAttempt {
        return ClaimAttempt::query()->create([
            'tenant_id' => $tenantId,
            'order_reference' => $orderReference,
            'email_fingerprint' => $emailFingerprint,
            'claim_reference' => $claimReference,
            'outcome' => $outcome,
            'channel' => $channel,
            'attempted_at' => $at,
        ]);
    }

    /**
     * @param  callable(): void  $recordRefusal  called before throwing, so the rate-limited
     *                                           attempt is itself in the record
     */
    public function assertWithinLimit(
        string $tenantId,
        string $orderReference,
        string $emailFingerprint,
        CarbonImmutable $at,
        callable $recordRefusal,
    ): void {
        $max = (int) Config::get('customer-accounts.claim.max_attempts', 5);
        $window = (int) Config::get('customer-accounts.claim.attempt_window_minutes', 60);
        $since = $at->subMinutes($window);

        $byOrder = ClaimAttempt::query()
            ->where('tenant_id', $tenantId)
            ->where('order_reference', $orderReference)
            ->where('attempted_at', '>=', $since)
            ->count();

        $byAddress = ClaimAttempt::query()
            ->where('tenant_id', $tenantId)
            ->where('email_fingerprint', $emailFingerprint)
            ->where('attempted_at', '>=', $since)
            ->count();

        $worst = max($byOrder, $byAddress);

        if ($worst >= $max) {
            $recordRefusal();

            throw ClaimRateLimited::after($worst, $window);
        }
    }
}
