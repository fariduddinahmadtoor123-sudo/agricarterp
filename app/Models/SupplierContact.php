<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplierContact extends Model
{
    protected $fillable = [
        'supplier_id',
        'contact_person',
        'mobile_number',
        'sort_order',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function mobileRegistryEntry(): HasOne
    {
        return $this->hasOne(ContactMobileNumber::class, 'contact_person_id');
    }
}
