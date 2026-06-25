<?php

namespace App\Models\PurchasingInventory;

use App\Models\PurchasingInventory\Concerns\HasUuidPrimaryKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchasePaymentSheet extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'sheet_number',
        'status',
        'title',
        'sheet_date',
        'purchaser_id',
        'purchaser_name',
        'notes',
        'payment_sources',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sheet_date' => 'date',
            'payment_sources' => 'array',
        ];
    }

    public function vendorLines(): HasMany
    {
        return $this->hasMany(PurchasePaymentSheetVendorLine::class, 'sheet_id')->orderBy('sort_order');
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
