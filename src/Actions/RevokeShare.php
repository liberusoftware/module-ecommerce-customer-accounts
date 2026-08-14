<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CustomerAccounts\Events\ShareRevoked;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\ShareNotFound;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedListShare;

/**
 * Revoking is an act with a time and a reason, not a null.
 *
 * The row survives revoked so that "this link stopped working on Tuesday because
 * the owner revoked it" and "this link never existed" stay different answers.
 * Re-revoking is a no-op rather than an error: somebody clicking twice is not a
 * failure worth raising.
 */
final class RevokeShare
{
    public function __invoke(string $shareReference, string $reason = 'owner_revoked'): SavedListShare
    {
        $share = SavedListShare::query()->where('reference', $shareReference)->first();

        if ($share === null) {
            throw ShareNotFound::referenced($shareReference);
        }

        if (! $share->isLive()) {
            return $share;
        }

        $share->forceFill([
            'revoked_at' => CarbonImmutable::now(),
            'revoked_reason' => $reason,
        ])->save();

        Event::dispatch(new ShareRevoked(
            shareReference: $share->reference,
            tenantId: $share->tenant_id,
            listReference: (string) $share->list?->reference,
            reason: $reason,
        ));

        return $share;
    }
}
