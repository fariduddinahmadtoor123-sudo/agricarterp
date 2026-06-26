<?php

namespace App\Models\SalesPos;

use App\Models\Customer;
use App\Models\PurchasingInventory\Concerns\HasUuidPrimaryKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSale extends Model
{
    use HasUuidPrimaryKey;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_HELD = 'held';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'sale_number',
        'status',
        'sale_date',
        'name_lang',
        'customer_id',
        'customer_name',
        'customer_mobile',
        'store_key',
        'store_name',
        'payment_method',
        'amount_paid',
        'subtotal',
        'total',
        'return_total',
        'refund_total',
        'credit_return_total',
        'net_total',
        'notes',
        'held_label',
        'stock_applied',
        'print_paper_size',
        'print_controls',
        'created_by',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'amount_paid' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'return_total' => 'decimal:2',
            'refund_total' => 'decimal:2',
            'credit_return_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'stock_applied' => 'boolean',
            'print_controls' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PosSaleLine::class, 'sale_id')->orderBy('sort_order');
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
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_HELD], true);
    }
}
