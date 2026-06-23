<?php

namespace App\Filament\PurchasingInventory\Support;

use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseTableConfiguration
{
    /**
     * @return list<string>
     */
    public static function primaryFilterKeys(): array
    {
        return ['status', 'invoice_payment_status'];
    }

    public static function applyListLayout(Table $table): Table
    {
        return $table
            ->extraAttributes([
                'class' => 'agricart-contacts-list agricart-contacts-list-purchases',
            ])
            ->filters(static::filters(), layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(1)
            ->filtersFormWidth(Width::Large)
            ->deferFilters(false)
            ->hiddenFilterIndicators()
            ->filtersFormSchema(fn (array $filters): array => [
                PurchasingInventoryListToolbar::primaryFiltersGroup($filters, static::primaryFilterKeys(), [
                    'status' => 'agricart-pu-filter-status-wrap',
                    'invoice_payment_status' => 'agricart-pu-filter-payment-wrap',
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
                ->options(config('purchasing-inventory.sheet_statuses', []))
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('Status')
                    ->native(false)
                    ->extraAttributes(['class' => 'agricart-pu-filter-status'])),
            SelectFilter::make('invoice_payment_status')
                ->label('Payment')
                ->options(config('purchasing-inventory.purchase_invoice_payment_statuses', []))
                ->modifyFormFieldUsing(fn (Select $select): Select => $select
                    ->hiddenLabel()
                    ->placeholder('Payment')
                    ->native(false)
                    ->extraAttributes(['class' => 'agricart-pu-filter-payment'])),
        ];
    }
}
