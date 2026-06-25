<?php

namespace App\Filament\Settings\Support;

use Filament\Tables\Table;

class CompanySettingTableConfiguration
{
    public static function applyListLayout(Table $table): Table
    {
        return $table->extraAttributes([
            'class' => 'agricart-contacts-list agricart-contacts-list-company-settings',
        ]);
    }
}
