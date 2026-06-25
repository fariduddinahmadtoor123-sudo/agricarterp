<?php

namespace App\Filament\ProductCatalog\Schemas;

use App\Models\Unit;
use App\Rules\UniqueUnitAbbreviation;
use App\Rules\UniqueUnitEnglishName;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema, bool $readOnly = false, ?Unit $record = null): Schema
    {
        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->extraAttributes([
                'class' => 'agricart-unit-form' . ($readOnly ? ' agricart-unit-form-readonly' : ''),
            ])
            ->components([
                Group::make()
                    ->schema([
                        TextInput::make('name_en')
                            ->label('English Unit Name')
                            ->placeholder('e.g. Kilogram, Liter, Piece')
                            ->required()
                            ->maxLength(100)
                            ->rules($readOnly ? [] : [new UniqueUnitEnglishName($record)])
                            ->validationMessages([
                                'required' => 'English unit name is required.',
                            ]),

                        TextInput::make('abbreviation_en')
                            ->label('English Abbreviation')
                            ->placeholder('e.g. kg, L, pcs')
                            ->required()
                            ->maxLength(20)
                            ->rules($readOnly ? [] : [new UniqueUnitAbbreviation($record)])
                            ->validationMessages([
                                'required' => 'English abbreviation is required.',
                            ]),

                        Select::make('unit_type')
                            ->label('Unit Type')
                            ->placeholder('Select unit type')
                            ->required()
                            ->native(false)
                            ->options(config('product-catalog.unit_types', []))
                            ->validationMessages([
                                'required' => 'Unit type is required.',
                            ]),
                    ])
                    ->columns(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'agricart-unit-entry-row',
                    ]),

                Section::make('System Information')
                    ->compact()
                    ->visible($record !== null)
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('unit_number_display')
                            ->label('Unit Number')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('is_standard_display')
                            ->label('Standard Unit')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('ai_status_display')
                            ->label('AI Status')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('ai_generated_at_display')
                            ->label('AI Generated At')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('ai_version_display')
                            ->label('AI Version')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columnSpanFull(),

                Section::make('Additional Information')
                    ->description('AI-generated and review content. Not required during fast unit entry.')
                    ->compact()
                    ->collapsed()
                    ->schema([
                        Fieldset::make('Urdu Identity')
                            ->schema([
                                TextInput::make('name_ur')
                                    ->label('Urdu Unit Name')
                                    ->maxLength(100)
                                    ->helperText('Optional during entry. AI will generate Urdu content later.'),

                                TextInput::make('abbreviation_ur')
                                    ->label('Urdu Abbreviation')
                                    ->maxLength(20),
                            ]),

                        Fieldset::make('Notes')
                            ->schema([
                                Textarea::make('usage_notes')
                                    ->label('Usage Notes')
                                    ->rows(3)
                                    ->helperText('Optional. AI may generate usage guidance later.'),
                            ]),
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
            'name_en' => null,
            'abbreviation_en' => null,
            'unit_type' => null,
            'name_ur' => null,
            'abbreviation_ur' => null,
            'usage_notes' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromModel(Unit $unit): array
    {
        return [
            'name_en' => $unit->name_en,
            'abbreviation_en' => $unit->abbreviation_en,
            'unit_type' => $unit->unit_type,
            'name_ur' => $unit->name_ur,
            'abbreviation_ur' => $unit->abbreviation_ur,
            'usage_notes' => $unit->usage_notes,
            'unit_number_display' => $unit->unit_number,
            'is_standard_display' => $unit->is_standard ? 'Yes' : 'No',
            'ai_status_display' => config('product-catalog.unit_ai_statuses')[$unit->ai_status] ?? ucfirst($unit->ai_status),
            'ai_generated_at_display' => $unit->ai_generated_at?->toDateTimeString(),
            'ai_version_display' => $unit->ai_version,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function normalizeState(array $state): array
    {
        unset(
            $state['unit_number_display'],
            $state['is_standard_display'],
            $state['ai_status_display'],
            $state['ai_generated_at_display'],
            $state['ai_version_display'],
        );

        return $state;
    }
}
