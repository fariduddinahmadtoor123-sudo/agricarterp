<?php

namespace App\Models\PurchasingInventory;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStoreCost extends Model
{
    protected $fillable = [
        'product_id',
        'store_key',
        'average_cost',
        'last_purchase_cost',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'average_cost' => 'decimal:4',
            'last_purchase_cost' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
