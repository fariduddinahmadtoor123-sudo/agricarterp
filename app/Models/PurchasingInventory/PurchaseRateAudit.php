<?php

namespace App\Models\PurchasingInventory;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRateAudit extends Model
{
    protected $fillable = [
        'product_id',
        'store_key',
        'previous_average_cost',
        'new_average_cost',
        'previous_last_purchase_cost',
        'new_last_purchase_cost',
        'purchase_rate',
        'received_quantity',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_average_cost' => 'decimal:4',
            'new_average_cost' => 'decimal:4',
            'previous_last_purchase_cost' => 'decimal:4',
            'new_last_purchase_cost' => 'decimal:4',
            'purchase_rate' => 'decimal:4',
            'received_quantity' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
