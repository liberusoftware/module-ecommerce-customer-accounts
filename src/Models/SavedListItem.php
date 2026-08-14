<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product reference and a quantity. No price column exists on this table, and
 * none is computed here.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $saved_list_id
 * @property string $product_ref
 * @property int $quantity
 * @property string|null $note
 * @property CarbonImmutable $added_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SavedList|null $list
 */
class SavedListItem extends Model
{
    protected $table = 'customer_accounts_saved_list_items';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'quantity' => 1,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'added_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(SavedList::class, 'saved_list_id');
    }
}
