<?php

namespace App\Models\PurchasingInventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReorderOrderLine extends Model
{
    protected $fillable = [
        'order_id',
        'line_id',
        'product_id',
        'sort_order',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ReorderOrder::class, 'order_id');
    }
}
