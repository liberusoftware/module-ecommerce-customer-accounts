<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person's list at one merchant.
 *
 * @property int $id
 * @property string $reference
 * @property string $tenant_id
 * @property string $owner_ref
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, SavedListItem> $items
 * @property-read Collection<int, SavedListShare> $shares
 */
class SavedList extends Model
{
    protected $table = 'customer_accounts_saved_lists';

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SavedListItem::class, 'saved_list_id')
            ->where('tenant_id', (string) $this->tenant_id);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(SavedListShare::class, 'saved_list_id')
            ->where('tenant_id', (string) $this->tenant_id);
    }

    /** Shares that still resolve. A revoked share is a row, not a deletion. */
    public function liveShares(): HasMany
    {
        return $this->shares()->whereNull('revoked_at');
    }
}
