<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserApplicationBankAccount extends Model
{
    protected $fillable = [
        'user_application_id',
        'bank_name',
        'branch_name',
        'account_title',
        'iban_account_number',
        'sort_order',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(UserApplication::class, 'user_application_id');
    }
}
