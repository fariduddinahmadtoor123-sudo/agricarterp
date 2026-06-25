<?php

namespace App\Filament\ProductCatalog\Support;

use App\Models\Brand;
use App\Services\ProductCatalog\BrandCategoryQuery;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BrandTableConfiguration
{
    /**
     * @return list<string>
     */
    public static function primaryFilterKeys(): array
    {
        return ['status', 'category_id'];
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
        $table = $table
            ->extraAttributes([
                'class' => 'agricart-contacts-list agricart-contacts-list-brands',
            ])
            ->filters(static::filters(), layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(1)
            ->filtersFormWidth(Width::Large)
            ->deferFilters(false)
            ->hiddenFilterIndicators()
            ->filtersFormSchema(fn (array $filters): array => [
                ProductCatalogListToolbar::primaryFiltersGroup($filters, static::primaryFilterKeys(), [
                    'status' => 'agricart-brand-filter-status-wrap',
                    'category_id' => 'agricart-brand-filter-category-wrap',
                ]),
            ]);

        if (ProductCatalogListToolbar::hasMoreFilters(static::moreFilterKeys())) {
            $table->filtersTriggerAction(fn ($action) => ProductCatalogListToolbar::configureMoreFiltersTrigger($action));
        }

        return $table;
    }

    /**
     * @return array<int, SelectFilter>
     */
    public static function filters(): array
    {
        $categories = app(BrandCategoryQuery::class);

        return [
            SelectFilter::make('status')
                ->label('Status')
                ->options(config('product-catalog.brand_statuses', []))
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('Status')
                    ->native(false)
                    ->extraAttributes(['class' => 'agricart-brand-filter-status'])),

            SelectFilter::make('category_id')
                ->label('Assigned Category')
                ->searchable()
                ->options(fn (): array => $categories->activeCategoryOptions())
                ->modifyFormFieldUsing(function (Select $select) use ($categories): Select {
                    return $select
                        ->hiddenLabel()
                        ->placeholder('Category')
                        ->native(false)
                        ->getSearchResultsUsing(fn (string $search): array => $categories->searchActiveCategories($search))
                        ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? $categories->categoryLabel($value) : null)
                        ->extraAttributes(['class' => 'agricart-brand-filter-category']);
                })
                ->query(function (Builder $query, array $data): Builder {
                    $categoryId = $data['value'] ?? null;

                    if (blank($categoryId)) {
                        return $query;
                    }

                    return $query->whereHas('categories', fn (Builder $query): Builder => $query->where('categories.id', $categoryId));
                }),
        ];
    }
}
