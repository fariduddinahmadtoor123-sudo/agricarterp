<?php

namespace App\Filament\ProductCatalog\Support;

use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UnitTableConfiguration
{
    /**
     * @return list<string>
     */
    public static function primaryFilterKeys(): array
    {
        return ['status', 'unit_type'];
    }

    /**
     * @return list<string>
     */
    public static function moreFilterKeys(): array
    {
        return [];
    }

    public static function applyListLayout(Table $table): Table
    {
        return $table
            ->extraAttributes([
                'class' => 'agricart-contacts-list agricart-contacts-list-units',
            ])
            ->filters(static::filters(), layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(1)
            ->filtersFormWidth(Width::Large)
            ->deferFilters(false)
            ->hiddenFilterIndicators()
            ->filtersTriggerAction(fn ($action) => ProductCatalogListToolbar::configureMoreFiltersTrigger($action))
            ->filtersFormSchema(fn (array $filters): array => [
                ProductCatalogListToolbar::primaryFiltersGroup($filters, static::primaryFilterKeys(), [
                    'status' => 'agricart-unit-filter-status-wrap',
                    'unit_type' => 'agricart-unit-filter-type-wrap',
                ]),
            ]);
    }

    /**
     * @return array<int, SelectFilter>
     */
    public static function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->label('Status')
                ->options(config('product-catalog.unit_statuses', []))
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('Status')
                    ->native(false)
                    ->extraAttributes(['class' => 'agricart-unit-filter-status'])),

            SelectFilter::make('unit_type')
                ->label('Unit Type')
                ->options(config('product-catalog.unit_types', []))
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('Unit Type')
                    ->native(false)
                    ->extraAttributes(['class' => 'agricart-unit-filter-type'])),
        ];
    }
}
