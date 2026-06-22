<?php

namespace App\Filament\ProductCatalog\Schemas;

use App\Models\Attribute;
use App\Rules\UniqueAttributeName;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttributeForm
{
    public static function configure(Schema $schema, bool $readOnly = false, ?Attribute $record = null): Schema
    {
        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->extraAttributes([
                'class' => 'agricart-attribute-form' . ($readOnly ? ' agricart-attribute-form-readonly' : ''),
            ])
            ->components([
                Group::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('Attribute Name')
                            ->placeholder('e.g. Color, Weight, Thread Size')
                            ->required()
                            ->maxLength(100)
                            ->rules($readOnly ? [] : [new UniqueAttributeName($record)])
                            ->validationMessages([
                                'required' => 'Attribute name is required.',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'agricart-attribute-entry-row',
                    ]),

                Section::make('System Information')
                    ->compact()
                    ->visible($record !== null)
                    ->schema([
                        TextInput::make('attribute_number_display')
                            ->label('Attribute Number')
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromModel(Attribute $attribute): array
    {
        return [
            'name' => $attribute->name,
            'attribute_number_display' => $attribute->attribute_number,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function normalizeState(array $state): array
    {
        unset($state['attribute_number_display']);

        return $state;
    }
}
