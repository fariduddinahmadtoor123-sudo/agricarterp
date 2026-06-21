<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerContact extends Model
{
    protected $fillable = [
        'customer_id',
        'contact_person',
        'mobile_number',
        'sort_order',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function mobileNumber(): HasOne
    {
        return $this->hasOne(ContactMobileNumber::class, 'contact_person_id');
    }
}
