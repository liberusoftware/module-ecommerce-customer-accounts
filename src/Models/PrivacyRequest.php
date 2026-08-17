<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Liberu\Ecommerce\CustomerAccounts\Data\Deadline;
use Liberu\Ecommerce\CustomerAccounts\Data\RequestProgress;
use Liberu\Ecommerce\CustomerAccounts\Enums\Channel;
use Liberu\Ecommerce\CustomerAccounts\Enums\LawfulBasis;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;

/**
 * The case.
 *
 * @property int $id
 * @property string $reference
 * @property string $tenant_id
 * @property string $subject_ref
 * @property RequestKind $kind
 * @property RequestScope $scope
 * @property LawfulBasis $lawful_basis
 * @property RequestState $state
 * @property Channel $channel
 * @property string|null $requested_by_ref
 * @property string|null $reason
 * @property string|null $reauthenticated_via
 * @property string $deadline_date
 * @property string $deadline_timezone
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable $recorded_at
 * @property CarbonImmutable|null $concluded_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, PrivacyRequestParticipant> $participants
 */
class PrivacyRequest extends Model
{
    use RestatesTenant;

    protected $table = 'customer_accounts_privacy_requests';

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => RequestKind::class,
            'scope' => RequestScope::class,
            'lawful_basis' => LawfulBasis::class,
            'state' => RequestState::class,
            'channel' => Channel::class,
            'deadline_date' => 'string',
            'requested_at' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
            'concluded_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Tenant-safe by construction: the participant rows carry the case's own
     * tenant, and the relation restates it rather than trusting the foreign key
     * alone. Wave 14 found the previous wave's custody proof was written about
     * queries while the leak was in a relation, so relations restate it here —
     * guarded, because the obvious restatement zeroes every count. See
     * {@see RestatesTenant}.
     */
    public function participants(): HasMany
    {
        return $this->scopedToTenant($this->hasMany(PrivacyRequestParticipant::class, 'privacy_request_id'));
    }

    public function deadline(): Deadline
    {
        return new Deadline($this->deadline_date, $this->deadline_timezone);
    }

    /**
     * The fold that decides whether this case may be called done.
     *
     * Computed from the rows every time. A stored counter is a second source of
     * truth about whether somebody's data was erased, and the first thing to
     * drift.
     */
    public function progress(): RequestProgress
    {
        $participants = $this->participants()->orderBy('participant')->get();

        $completed = 0;
        $unavailable = 0;
        $failed = 0;
        $pending = 0;
        $outstanding = [];
        $mismatched = [];

        foreach ($participants as $participant) {
            match ($participant->outcome->value) {
                'completed' => $completed++,
                'unavailable' => $unavailable++,
                'failed' => $failed++,
                default => $pending++,
            };

            if (! $participant->outcome->isSatisfied()) {
                $outstanding[] = $participant->participant;
            }

            if ($participant->scope_mismatch) {
                $mismatched[] = $participant->participant;
            }
        }

        return new RequestProgress(
            participants: $participants->count(),
            completed: $completed,
            unavailable: $unavailable,
            failed: $failed,
            pending: $pending,
            outstanding: $outstanding,
            mismatched: $mismatched,
        );
    }
}
