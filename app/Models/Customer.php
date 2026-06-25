<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    public const OPENING_BALANCE_DEBIT = 'debit';

    public const OPENING_BALANCE_CREDIT = 'credit';

    protected $fillable = [
        'customer_code',
        'customer_name',
        'mobile_number',
        'country',
        'city',
        'full_address',
        'credit_limit',
        'opening_balance',
        'opening_balance_type',
        'ledger_account_id',
        'urdu_customer_name',
        'urdu_city',
        'urdu_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'opening_balance' => 'decimal:2',
        ];
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(CustomerBankAccount::class)->orderBy('sort_order');
    }

    public function additionalContacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class)->orderBy('sort_order');
    }

    public function document(): HasOne
    {
        return $this->hasOne(CustomerDocument::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function mobileNumbers(): MorphMany
    {
        return $this->morphMany(ContactMobileNumber::class, 'contactable');
    }

    public function getMorphClass(): string
    {
        return ContactMobileNumber::CONTACTABLE_CUSTOMER;
    }

    /**
     * Customers available in operational dropdowns (e.g. future Sales module).
     */
    public function scopeOperational($query)
    {
        return $query->whereNull('deleted_at');
    }
}
