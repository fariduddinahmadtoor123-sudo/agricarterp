<?php

namespace App\Filament\ProductCatalog\Schemas;

use App\Models\ProductControlGroup;
use App\Rules\UniqueProductControlGroupName;
use App\Services\ProductCatalog\ProductControlQuery;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductControlGroupForm
{
    public static function configure(Schema $schema, bool $readOnly = false, ?ProductControlGroup $record = null): Schema
    {
        $controls = app(ProductControlQuery::class);

        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->extraAttributes([
                'class' => 'agricart-product-control-group-form' . ($readOnly ? ' agricart-product-control-group-form-readonly' : ''),
            ])
            ->components([
                Group::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('Group Name')
                            ->placeholder('e.g. Motor Standard Policy')
                            ->required()
                            ->maxLength(200)
                            ->rules($readOnly ? [] : [new UniqueProductControlGroupName($record)])
                            ->validationMessages([
                                'required' => 'Group name is required.',
                            ])
                            ->columnSpanFull(),

                        Select::make('control_ids')
                            ->label('Assigned Controls')
                            ->placeholder('Search controls...')
                            ->multiple()
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->options(fn (): array => $controls->activeControlOptions())
                            ->getSearchResultsUsing(fn (string $search): array => $controls->searchActiveControls($search))
                            ->getOptionLabelsUsing(function (array $values) use ($controls): array {
                                $labels = [];

                                foreach ($values as $value) {
                                    $labels[$value] = $controls->controlLabel($value) ?? (string) $value;
                                }

                                return $labels;
                            })
                            ->helperText('Select reusable controls to include in this policy group.')
                            ->validationMessages([
                                'required' => 'At least one control must be assigned to the group.',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'agricart-product-control-group-entry-row',
                    ]),

                Section::make('System Information')
                    ->compact()
                    ->visible($record !== null)
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('group_number_display')
                            ->label('Group Number')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('controls_count_display')
                            ->label('Controls Count')
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
            'control_ids' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromModel(ProductControlGroup $group): array
    {
        return [
            'name' => $group->name,
            'control_ids' => $group->controls->pluck('id')->all(),
            'group_number_display' => $group->group_number,
            'controls_count_display' => (string) $group->controls_count,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function normalizeState(array $state): array
    {
        unset(
            $state['group_number_display'],
            $state['controls_count_display'],
        );

        return $state;
    }
}
