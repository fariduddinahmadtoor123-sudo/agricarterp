<?php

namespace App\Models\SalesPos;

use App\Models\Customer;
use App\Models\PurchasingInventory\Concerns\HasUuidPrimaryKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturn extends Model
{
    use HasUuidPrimaryKey;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VOID = 'void';

    public const REFUND_PENDING = 'pending';

    public const REFUND_PAID = 'paid';

    public const REFUND_CREDITED = 'credited';

    protected $fillable = [
        'return_number',
        'status',
        'return_date',
        'pos_sale_id',
        'sale_number',
        'name_lang',
        'customer_id',
        'customer_name',
        'customer_mobile',
        'store_key',
        'store_name',
        'original_payment_method',
        'refund_method',
        'refund_status',
        'return_subtotal',
        'return_total',
        'refund_amount',
        'credit_amount',
        'notes',
        'refund_notes',
        'stock_applied',
        'created_by',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'return_subtotal' => 'decimal:2',
            'return_total' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'stock_applied' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesReturnLine::class, 'return_id')->orderBy('sort_order');
    }

    public function posSale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
