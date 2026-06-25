<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchasePricingSetting extends Model
{
    protected $fillable = [
        'update_product_prices_from_purchases',
        'wholesale_markup_pct',
        'super_wholesale_markup_pct',
        'distributor_markup_pct',
        'price_code_wording',
    ];

    protected function casts(): array
    {
        return [
            'update_product_prices_from_purchases' => 'boolean',
            'wholesale_markup_pct' => 'decimal:2',
            'super_wholesale_markup_pct' => 'decimal:2',
            'distributor_markup_pct' => 'decimal:2',
            'price_code_wording' => 'array',
        ];
    }
}
