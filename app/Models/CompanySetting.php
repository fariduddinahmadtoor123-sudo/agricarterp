<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = [
        'logo_path',
        'name_en',
        'name_ur',
        'address_en',
        'address_ur',
        'phones',
        'whatsapp_numbers',
        'emails',
        'website_url',
        'ntn',
        'strn',
        'currency',
        'decimal_places',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'phones' => 'array',
            'whatsapp_numbers' => 'array',
            'emails' => 'array',
            'decimal_places' => 'integer',
        ];
    }
}
