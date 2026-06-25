<?php

namespace App\Models\PurchasingInventory;

use App\Models\PurchasingInventory\Concerns\HasUuidPrimaryKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReorderOrder extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'order_number',
        'purchaser_id',
        'purchaser_name',
        'name_lang',
        'status',
        'sent_at',
        'received_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReorderOrderLine::class, 'order_id')->orderBy('sort_order');
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(Purchaser::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
