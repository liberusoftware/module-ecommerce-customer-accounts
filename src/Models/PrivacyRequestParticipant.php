<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Liberu\Ecommerce\CustomerAccounts\Enums\ParticipantOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;

/**
 * One participant's answer, or its conspicuous absence.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $privacy_request_id
 * @property string $participant
 * @property string $label
 * @property ParticipantOutcome $outcome
 * @property RequestScope|null $scope_applied
 * @property bool $scope_mismatch
 * @property array<string, mixed>|null $summary
 * @property array<string, mixed>|null $payload
 * @property string|null $note
 * @property int $attempts
 * @property CarbonImmutable|null $answered_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read PrivacyRequest|null $request
 */
class PrivacyRequestParticipant extends Model
{
    protected $table = 'customer_accounts_privacy_request_participants';

    protected $guarded = [];

    /**
     * `create()` does not read a column default back, so the defaults live in
     * both places. A boolean that arrives null where the docblock says bool
     * passes every test in this package and fails in the first surface that
     * assigns it to a typed parameter.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'outcome' => 'pending',
        'scope_mismatch' => false,
        'attempts' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'outcome' => ParticipantOutcome::class,
            'scope_applied' => RequestScope::class,
            'scope_mismatch' => 'boolean',
            'summary' => 'array',
            'payload' => 'array',
            'attempts' => 'integer',
            'answered_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PrivacyRequest::class, 'privacy_request_id');
    }
}
