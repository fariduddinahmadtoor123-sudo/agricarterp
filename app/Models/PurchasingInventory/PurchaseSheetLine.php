<?php

namespace App\Models\PurchasingInventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseSheetLine extends Model
{
    protected $fillable = [
        'sheet_id',
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

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(PurchaseSheet::class, 'sheet_id');
    }
}
