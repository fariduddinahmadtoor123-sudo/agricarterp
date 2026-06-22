<?php

namespace App\Filament\ProductCatalog\Support;

use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttributeTableConfiguration
{
    /**
     * @return list<string>
     */
    public static function primaryFilterKeys(): array
    {
        return ['status'];
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
                'class' => 'agricart-contacts-list agricart-contacts-list-attributes',
            ])
            ->filters(static::filters(), layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(1)
            ->filtersFormWidth(Width::Large)
            ->deferFilters(false)
            ->hiddenFilterIndicators()
            ->filtersTriggerAction(fn ($action) => ProductCatalogListToolbar::configureMoreFiltersTrigger($action))
            ->filtersFormSchema(fn (array $filters): array => [
                ProductCatalogListToolbar::primaryFiltersGroup($filters, static::primaryFilterKeys(), [
                    'status' => 'agricart-attribute-filter-status-wrap',
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
                ->options(config('product-catalog.attribute_statuses', []))
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('Status')
                    ->native(false)
                    ->extraAttributes(['class' => 'agricart-attribute-filter-status'])),
        ];
    }
}
