<?php

namespace App\Filament\PurchasingInventory\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PurchasePlanningNotesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('notes')
                ->label('Sheet Notes')
                ->rows(6)
                ->placeholder('Planning notes, supplier reminders, seasonal context, import instructions...'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $sheet
     * @return array<string, mixed>
     */
    public static function fromSheet(array $sheet): array
    {
        return [
            'notes' => (string) ($sheet['notes'] ?? ''),
        ];
    }
}
