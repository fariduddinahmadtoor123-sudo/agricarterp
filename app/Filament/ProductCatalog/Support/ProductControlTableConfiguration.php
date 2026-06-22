<?php

namespace App\Filament\ProductCatalog\Support;

use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductControlTableConfiguration
{
    /**
     * @return list<string>
     */
    public static function primaryFilterKeys(): array
    {
        return ['status'];
    }

    public static function applyListLayout(Table $table): Table
    {
        return $table
            ->extraAttributes([
                'class' => 'agricart-contacts-list agricart-contacts-list-controls',
            ])
            ->filters(static::filters(), layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(1)
            ->filtersFormWidth(Width::Large)
            ->deferFilters(false)
            ->hiddenFilterIndicators()
            ->filtersTriggerAction(fn ($action) => ProductCatalogListToolbar::configureMoreFiltersTrigger($action))
            ->filtersFormSchema(fn (array $filters): array => [
                ProductCatalogListToolbar::primaryFiltersGroup($filters, static::primaryFilterKeys(), [
                    'status' => 'agricart-control-filter-status-wrap',
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
                ->options(config('product-catalog.control_statuses', []))
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('Status')
                    ->native(false)
                    ->extraAttributes(['class' => 'agricart-control-filter-status'])),
        ];
    }
}
