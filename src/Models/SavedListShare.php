<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A share: its own aggregate, its own token, its own revocation.
 *
 * @property int $id
 * @property string $reference
 * @property string $tenant_id
 * @property int $saved_list_id
 * @property string $token_fingerprint
 * @property string $owner_ref
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoked_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SavedList|null $list
 */
class SavedListShare extends Model
{
    protected $table = 'customer_accounts_saved_list_shares';

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(SavedList::class, 'saved_list_id');
    }

    public function isLive(): bool
    {
        return $this->revoked_at === null;
    }
}
