<?php

namespace App\Models\PurchasingInventory;

use App\Models\PurchasingInventory\Concerns\HasUuidPrimaryKey;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseSheet extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'purchase_number',
        'status',
        'title',
        'sheet_date',
        'name_lang',
        'notes',
        'supplier_id',
        'supplier_name',
        'store_key',
        'store_name',
        'invoice_payment_status',
        'goods_receipt_status',
        'dispute_status',
        'dispute_notes',
        'invoice_image_path',
        'linked_planning_id',
        'linked_planning_number',
        'linked_quotation_id',
        'linked_quotation_number',
        'payment_amount',
        'payment_notes',
        'print_paper_size',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sheet_date' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseSheetLine::class, 'sheet_id')->orderBy('sort_order');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
