<?php

namespace App\Filament\Contacts\Support;

use App\Models\Supplier;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupplierTableConfiguration
{
    /**
     * @return list<string>
     */
    public static function primaryFilterKeys(): array
    {
        return ['status', 'city', 'supplier_type'];
    }

    /**
     * @return list<string>
     */
    public static function moreFilterKeys(): array
    {
        return ['country', 'credit_limit', 'created_at', 'trashed'];
    }

    public static function applyListLayout(Table $table): Table
    {
        return $table
            ->extraAttributes([
                'class' => 'agricart-contacts-list agricart-contacts-list-suppliers',
            ])
            ->filters(static::filters(), layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(1)
            ->filtersFormWidth(Width::Large)
            ->deferFilters(false)
            ->hiddenFilterIndicators()
            ->filtersTriggerAction(fn ($action) => ContactsListToolbar::configureMoreFiltersTrigger($action))
            ->filtersFormSchema(fn (array $filters): array => [
                ContactsListToolbar::primaryFiltersGroup($filters, static::primaryFilterKeys(), [
                    'status' => 'agricart-supplier-filter-status-wrap',
                    'city' => 'agricart-supplier-filter-city-wrap',
                    'supplier_type' => 'agricart-supplier-filter-type-wrap',
                ]),
                ...ContactsListToolbar::moreFilterComponents($filters, static::moreFilterKeys()),
            ]);
    }

    /**
     * @return array<int, SelectFilter|Filter|TrashedFilter>
     */
    public static function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->label('Status')
                ->options(config('contacts.supplier_statuses', []))
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('Status')
                    ->native(false)
                    ->extraAttributes(['class' => 'agricart-supplier-filter-status'])),

            SelectFilter::make('city')
                ->label('City')
                ->searchable()
                ->options(fn (): array => static::distinctCityOptions())
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('City')
                    ->native(false)
                    ->extraAttributes(['class' => 'agricart-supplier-filter-city'])),

            SelectFilter::make('supplier_type')
                ->label('Type')
                ->options([
                    'local' => 'Local Supplier',
                    'importer' => 'Importer',
                    'manufacturer' => 'Manufacturer',
                    'distributor' => 'Distributor',
                ])
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('Type')
                    ->native(false)
                    ->extraAttributes(['class' => 'agricart-supplier-filter-type'])),

            SelectFilter::make('country')
                ->label('Country')
                ->searchable()
                ->options(config('contacts.countries', []))
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('Country')
                    ->native(false)),

            static::creditLimitFilter(),

            static::createdAtFilter(),

            TrashedFilter::make()
                ->visible(fn (): bool => \App\Support\Contacts\SupplierAuthorization::canRestore()),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function distinctCityOptions(): array
    {
        return Supplier::query()
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city', 'city')
            ->all();
    }

    protected static function creditLimitFilter(): Filter
    {
        return Filter::make('credit_limit')
            ->label('Credit Limit')
            ->schema([
                TextInput::make('min')
                    ->label('From')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('max')
                    ->label('To')
                    ->numeric()
                    ->minValue(0),
            ])
            ->columns(2)
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when(
                        filled($data['min'] ?? null),
                        fn (Builder $query): Builder => $query->where('credit_limit', '>=', $data['min']),
                    )
                    ->when(
                        filled($data['max'] ?? null),
                        fn (Builder $query): Builder => $query->where('credit_limit', '<=', $data['max']),
                    );
            })
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if (filled($data['min'] ?? null)) {
                    $indicators[] = 'Credit limit from ' . $data['min'];
                }

                if (filled($data['max'] ?? null)) {
                    $indicators[] = 'Credit limit to ' . $data['max'];
                }

                return $indicators;
            });
    }

    protected static function createdAtFilter(): Filter
    {
        return Filter::make('created_at')
            ->label('Created Date')
            ->schema([
                DatePicker::make('from')
                    ->label('From'),
                DatePicker::make('until')
                    ->label('Until'),
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
            })
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if (filled($data['from'] ?? null)) {
                    $indicators[] = 'Created from ' . $data['from'];
                }

                if (filled($data['until'] ?? null)) {
                    $indicators[] = 'Created until ' . $data['until'];
                }

                return $indicators;
            });
    }
}
