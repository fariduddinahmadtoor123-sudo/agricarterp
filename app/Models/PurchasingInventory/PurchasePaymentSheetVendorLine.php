<?php

namespace App\Models\PurchasingInventory;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePaymentSheetVendorLine extends Model
{
    protected $fillable = [
        'sheet_id',
        'sort_order',
        'supplier_id',
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
        return $this->belongsTo(PurchasePaymentSheet::class, 'sheet_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
