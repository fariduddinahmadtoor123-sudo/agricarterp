<?php

namespace App\Models\PurchasingInventory;

use App\Models\PurchasingInventory\Concerns\HasUuidPrimaryKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpeningStockEntry extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'entry_number',
        'status',
        'store_key',
        'notes',
        'applied_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OpeningStockEntryLine::class, 'entry_id')->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
