<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Liberu\Ecommerce\CustomerAccounts\Enums\Channel;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimState;

/**
 * Evidence that a past guest transaction belongs to this person.
 *
 * @property int $id
 * @property string $reference
 * @property string $tenant_id
 * @property string $order_reference
 * @property string|null $claimant_ref
 * @property string|null $email_fingerprint
 * @property ClaimState $state
 * @property Channel $channel
 * @property string|null $token_fingerprint
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable $recorded_at
 * @property CarbonImmutable|null $granted_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, ClaimAttempt> $attempts
 */
class OrderClaim extends Model
{
    protected $table = 'customer_accounts_order_claims';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'state' => 'pending',
    ];

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => ClaimState::class,
            'channel' => Channel::class,
            'expires_at' => 'immutable_datetime',
            'requested_at' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
            'granted_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * The attempts against this order reference — **the** relation this module's
     * custody proof is about.
     *
     * It joins on `order_reference`, which is a reference from another module
     * that is unique only within a merchant. Two merchants both using order
     * number 1001 is the ordinary case, not a contrived one, so without the
     * tenant restatement this relation shows merchant A the attempts somebody
     * made against merchant B's order of the same number — a foreign-key-free
     * join is exactly where a tenant leak hides.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(ClaimAttempt::class, 'order_reference', 'order_reference')
            ->where('tenant_id', (string) $this->tenant_id);
    }

    public function hasExpired(CarbonImmutable $at): bool
    {
        return $this->expires_at !== null && $at->greaterThan($this->expires_at);
    }
}
