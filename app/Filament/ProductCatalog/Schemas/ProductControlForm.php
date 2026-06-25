<?php

namespace App\Filament\ProductCatalog\Schemas;

use App\Models\ProductControl;
use App\Rules\UniqueProductControlName;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductControlForm
{
    public static function configure(Schema $schema, bool $readOnly = false, ?ProductControl $record = null): Schema
    {
        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->extraAttributes([
                'class' => 'agricart-product-control-form' . ($readOnly ? ' agricart-product-control-form-readonly' : ''),
            ])
            ->components([
                Group::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('Control Name')
                            ->placeholder('e.g. Handle with care, Motor winding covered')
                            ->required()
                            ->maxLength(500)
                            ->rules($readOnly ? [] : [new UniqueProductControlName($record)])
                            ->validationMessages([
                                'required' => 'Control name is required.',
                            ])
                            ->columnSpanFull(),

                        Select::make('control_type')
                            ->label('Control Type')
                            ->placeholder('Select control type')
                            ->required()
                            ->native(false)
                            ->options(config('product-catalog.control_types', []))
                            ->validationMessages([
                                'required' => 'Control type is required.',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'agricart-product-control-entry-row',
                    ]),

                Section::make('System Information')
                    ->compact()
                    ->visible($record !== null)
                    ->schema([
                        TextInput::make('control_number_display')
                            ->label('Control Number')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultState(): array
    {
        return [
            'name' => null,
            'control_type' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromModel(ProductControl $control): array
    {
        return [
            'name' => $control->name,
            'control_type' => $control->control_type,
            'control_number_display' => $control->control_number,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function normalizeState(array $state): array
    {
        unset($state['control_number_display']);

        return $state;
    }
}
