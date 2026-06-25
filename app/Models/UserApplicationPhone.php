<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserApplicationPhone extends Model
{
    protected $fillable = [
        'user_application_id',
        'contact_person',
        'phone_number',
        'sort_order',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(UserApplication::class, 'user_application_id');
    }
}
