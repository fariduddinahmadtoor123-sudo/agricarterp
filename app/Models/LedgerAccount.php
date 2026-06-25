<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerAccount extends Model
{
    public const TYPE_PAYABLE = 'payable';

    protected $fillable = [
        'account_code',
        'name',
        'account_type',
        'contact_type',
        'contact_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
