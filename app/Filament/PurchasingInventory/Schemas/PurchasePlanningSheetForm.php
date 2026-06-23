<?php

namespace App\Filament\PurchasingInventory\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PurchasePlanningSheetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('notes')
                ->label('Initial Notes')
                ->rows(4)
                ->placeholder('Optional planning context for this sheet...'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultState(): array
    {
        return [
            'notes' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function normalizeState(array $state): array
    {
        return [
            'notes' => trim((string) ($state['notes'] ?? '')),
        ];
    }
}
