<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContactMobileNumber extends Model
{
    public const CONTACTABLE_SUPPLIER = 'supplier';

    public const CONTACTABLE_CUSTOMER = 'customer';

    public const CATEGORY_PRIMARY = 'primary';

    public const CATEGORY_WHATSAPP = 'whatsapp';

    public const CATEGORY_ADDITIONAL = 'additional';

    protected $fillable = [
        'mobile_normalized',
        'contactable_type',
        'contactable_id',
        'category',
        'contact_person_id',
    ];

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function supplierContact(): BelongsTo
    {
        return $this->belongsTo(SupplierContact::class, 'contact_person_id');
    }

    public function customerContact(): BelongsTo
    {
        return $this->belongsTo(CustomerContact::class, 'contact_person_id');
    }
}
