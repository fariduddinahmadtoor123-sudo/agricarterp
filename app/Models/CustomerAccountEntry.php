<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAccountEntry extends Model
{
    public const TYPE_SALE_RETURN_CREDIT = 'sale_return_credit';

    protected $fillable = [
        'customer_id',
        'entry_type',
        'amount',
        'reference_type',
        'reference_id',
        'description',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
