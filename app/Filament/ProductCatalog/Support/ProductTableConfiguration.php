<?php

namespace App\Filament\ProductCatalog\Support;

use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductTableConfiguration
{
    /**
     * @return list<string>
     */
    public static function primaryFilterKeys(): array
    {
        return ['status', 'category_id', 'brand_id'];
    }

    public static function applyListLayout(Table $table): Table
    {
        $table = $table
            ->extraAttributes([
                'class' => 'agricart-contacts-list agricart-contacts-list-products',
            ])
            ->filters(static::filters(), layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(1)
            ->filtersFormWidth(Width::Large)
            ->deferFilters(false)
            ->hiddenFilterIndicators()
            ->filtersFormSchema(fn (array $filters): array => [
                ProductCatalogListToolbar::primaryFiltersGroup($filters, static::primaryFilterKeys(), [
                    'status' => 'agricart-product-filter-status-wrap',
                    'category_id' => 'agricart-product-filter-category-wrap',
                    'brand_id' => 'agricart-product-filter-brand-wrap',
                ]),
            ]);

        return $table;
    }

    /**
     * @return array<int, SelectFilter>
     */
    public static function filters(): array
    {
        $categories = app(\App\Services\ProductCatalog\ProductCategoryQuery::class);
        $masters = app(\App\Services\ProductCatalog\ProductCatalogMasterQuery::class);

        return [
            SelectFilter::make('status')
                ->label('Status')
                ->options(config('product-catalog.product_statuses', []))
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('Status')
                    ->native(false)
                    ->default(Product::STATUS_ACTIVE)
                    ->extraAttributes(['class' => 'agricart-product-filter-status'])),

            SelectFilter::make('category_id')
                ->label('Primary Category')
                ->options(fn (): array => $categories->activeLeafCategoryOptions())
                ->searchable()
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('Primary Category')
                    ->native(false)
                    ->extraAttributes(['class' => 'agricart-product-filter-category'])),

            SelectFilter::make('brand_id')
                ->label('Brand')
                ->options(fn (): array => $masters->activeBrandOptions())
                ->searchable()
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('Brand')
                    ->native(false)
                    ->extraAttributes(['class' => 'agricart-product-filter-brand'])),
        ];
    }
}
