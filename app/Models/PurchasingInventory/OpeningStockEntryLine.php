<?php

namespace App\Models\PurchasingInventory;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningStockEntryLine extends Model
{
    protected $fillable = [
        'entry_id',
        'line_id',
        'product_id',
        'quantity',
        'unit_cost',
        'payload',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'payload' => 'array',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(OpeningStockEntry::class, 'entry_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
