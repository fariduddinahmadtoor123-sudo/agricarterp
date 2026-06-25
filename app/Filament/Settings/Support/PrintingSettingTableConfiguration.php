<?php

namespace App\Filament\Settings\Support;

use Filament\Tables\Table;

class PrintingSettingTableConfiguration
{
    public static function applyListLayout(Table $table): Table
    {
        return $table->extraAttributes([
            'class' => 'agricart-settings-list agricart-settings-list-printing',
        ]);
    }
}
