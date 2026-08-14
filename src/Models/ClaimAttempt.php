<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\CustomerAccounts\Enums\Channel;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimAttemptOutcome;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\AttemptsAreAppendOnly;

/**
 * One attempt at a claim. Append-only.
 *
 * The guard is in a model hook, and a model hook does not fire for
 * `query()->update()` or `query()->delete()` — a hole this fleet has now found
 * five times. So the guarantee is stated in two places: nothing in this package
 * writes attempts through the builder, and the boundary suite asserts that no
 * source file does.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $order_reference
 * @property string $email_fingerprint
 * @property string|null $claim_reference
 * @property ClaimAttemptOutcome $outcome
 * @property Channel $channel
 * @property CarbonImmutable $attempted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class ClaimAttempt extends Model
{
    protected $table = 'customer_accounts_claim_attempts';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (ClaimAttempt $attempt): void {
            throw AttemptsAreAppendOnly::cannotUpdate($attempt->id);
        });

        static::deleting(function (ClaimAttempt $attempt): void {
            throw AttemptsAreAppendOnly::cannotDelete($attempt->id);
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'outcome' => ClaimAttemptOutcome::class,
            'channel' => Channel::class,
            'attempted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
