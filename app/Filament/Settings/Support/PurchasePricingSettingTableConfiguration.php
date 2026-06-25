<?php

namespace App\Filament\Settings\Support;

use Filament\Tables\Table;

class PurchasePricingSettingTableConfiguration
{
    public static function applyListLayout(Table $table): Table
    {
        return $table->extraAttributes([
            'class' => 'agricart-contacts-list agricart-contacts-list-purchase-pricing-settings',
        ]);
    }
}
