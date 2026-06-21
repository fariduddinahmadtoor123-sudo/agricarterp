<?php

namespace App\Filament\ProductCatalog\Support;

use App\Models\Category;
use App\Services\ProductCatalog\CategoryHierarchyService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoryTableConfiguration
{
    /**
     * @return list<string>
     */
    public static function primaryFilterKeys(): array
    {
        return ['status', 'level', 'is_leaf'];
    }

    /**
     * @return list<string>
     */
    public static function moreFilterKeys(): array
    {
        return ['parent_id', 'has_products', 'created_at'];
    }

    public static function applyListLayout(Table $table): Table
    {
        return $table
            ->extraAttributes([
                'class' => 'agricart-contacts-list agricart-contacts-list-categories',
            ])
            ->filters(static::filters(), layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(1)
            ->filtersFormWidth(Width::Large)
            ->deferFilters(false)
            ->hiddenFilterIndicators()
            ->filtersTriggerAction(fn ($action) => ProductCatalogListToolbar::configureMoreFiltersTrigger($action))
            ->filtersFormSchema(fn (array $filters): array => [
                ProductCatalogListToolbar::primaryFiltersGroup($filters, static::primaryFilterKeys(), [
                    'status' => 'agricart-category-filter-status-wrap',
                    'level' => 'agricart-category-filter-level-wrap',
                    'is_leaf' => 'agricart-category-filter-leaf-wrap',
                ]),
                ...ProductCatalogListToolbar::moreFilterComponents($filters, static::moreFilterKeys()),
            ]);
    }

    /**
     * @return array<int, SelectFilter|TernaryFilter|Filter>
     */
    public static function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->label('Status')
                ->options(config('product-catalog.category_statuses', []))
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('Status')
                    ->native(false)
                    ->extraAttributes(['class' => 'agricart-category-filter-status'])),

            SelectFilter::make('level')
                ->label('Level')
                ->options(static::levelOptions())
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('Level')
                    ->native(false)
                    ->extraAttributes(['class' => 'agricart-category-filter-level'])),

            TernaryFilter::make('is_leaf')
                ->label('Leaf')
                ->placeholder('Leaf')
                ->trueLabel('Leaf only')
                ->falseLabel('Non-leaf only'),

            SelectFilter::make('parent_id')
                ->label('Parent Category')
                ->searchable()
                ->options(fn (): array => app(CategoryHierarchyService::class)->parentOptions()),

            Filter::make('has_products')
                ->label('Has Products')
                ->schema([
                    Select::make('value')
                        ->label('Has Products')
                        ->options([
                            'yes' => 'Yes',
                            'no' => 'No',
                        ])
                        ->native(false),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return match ($data['value'] ?? null) {
                        'yes' => $query->where('products_count', '>', 0),
                        'no' => $query->where('products_count', '=', 0),
                        default => $query,
                    };
                }),

            Filter::make('created_at')
                ->label('Created Date')
                ->schema([
                    DatePicker::make('from')->label('From'),
                    DatePicker::make('until')->label('Until'),
                ])
                ->columns(2)
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            filled($data['from'] ?? null),
                            fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $data['from']),
                        )
                        ->when(
                            filled($data['until'] ?? null),
                            fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $data['until']),
                        );
                }),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function levelOptions(): array
    {
        $maxLevel = (int) Category::query()->max('level');

        $options = [];

        for ($level = 0; $level <= max($maxLevel, 5); $level++) {
            $options[(string) $level] = 'Level ' . $level;
        }

        return $options;
    }
}
