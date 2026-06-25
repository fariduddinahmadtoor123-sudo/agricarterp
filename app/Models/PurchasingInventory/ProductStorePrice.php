<?php

namespace App\Models\PurchasingInventory;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStorePrice extends Model
{
    protected $fillable = [
        'product_id',
        'store_key',
        'purchase_rate',
        'landing_cost',
        'sale_rate',
        'wholesale_rate',
        'super_wholesale_rate',
        'distributor_rate',
        'source_purchase_sheet_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_rate' => 'decimal:4',
            'landing_cost' => 'decimal:4',
            'sale_rate' => 'decimal:4',
            'wholesale_rate' => 'decimal:4',
            'super_wholesale_rate' => 'decimal:4',
            'distributor_rate' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
