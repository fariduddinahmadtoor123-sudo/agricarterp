<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    public const OPENING_BALANCE_DEBIT = 'debit';

    public const OPENING_BALANCE_CREDIT = 'credit';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'supplier_code',
        'supplier_type',
        'status',
        'country',
        'city',
        'full_address',
        'business_name',
        'contact_name',
        'mobile_number',
        'credit_limit',
        'opening_balance',
        'opening_balance_type',
        'ledger_account_id',
        'urdu_business_name',
        'urdu_contact_name',
        'urdu_city',
        'urdu_account_title',
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
        return $this->hasMany(SupplierBankAccount::class)->orderBy('sort_order');
    }

    public function additionalContacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class)->orderBy('sort_order');
    }

    public function document(): HasOne
    {
        return $this->hasOne(SupplierDocument::class);
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
        return ContactMobileNumber::CONTACTABLE_SUPPLIER;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Suppliers available in operational dropdowns (e.g. future Purchase module).
     */
    public function scopeOperational($query)
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereNull('deleted_at');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }
}
