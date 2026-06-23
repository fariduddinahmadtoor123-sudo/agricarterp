<?php

namespace App\Filament\PurchasingInventory\Support;

use App\Filament\Pages\PurchasingInventory\PurchasePlanning;
use App\Filament\Pages\PurchasingInventory\PurchasePaymentSheet;
use App\Filament\Pages\PurchasingInventory\PurchaseQuotations;
use App\Filament\Pages\PurchasingInventory\Purchases;
use App\Filament\ProductCatalog\Support\ProductCatalogListToolbar;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;

class PurchasingInventoryListToolbar
{
    public static function register(): void
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_SEARCH_AFTER,
            fn (): string => '<span data-agricart-primary-filters-anchor class="agricart-contacts-primary-filters-anchor"></span>',
            PurchasePlanning::class,
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_SEARCH_AFTER,
            fn (): string => '<span data-agricart-primary-filters-anchor class="agricart-contacts-primary-filters-anchor"></span>',
            PurchaseQuotations::class,
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_SEARCH_AFTER,
            fn (): string => '<span data-agricart-primary-filters-anchor class="agricart-contacts-primary-filters-anchor"></span>',
            Purchases::class,
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_SEARCH_AFTER,
            fn (): string => '<span data-agricart-primary-filters-anchor class="agricart-contacts-primary-filters-anchor"></span>',
            PurchasePaymentSheet::class,
        );
    }

    /**
     * @param  array<string, \Filament\Schemas\Components\Group>  $filters
     * @param  list<string>  $keys
     * @param  array<string, string>  $wrapperClasses
     */
    public static function primaryFiltersGroup(array $filters, array $keys, array $wrapperClasses = []): \Filament\Schemas\Components\Group
    {
        return ProductCatalogListToolbar::primaryFiltersGroup($filters, $keys, $wrapperClasses);
    }

    /**
     * @param  array<string, \Filament\Schemas\Components\Group>  $filters
     * @param  list<string>  $keys
     * @return list<\Filament\Schemas\Components\Group>
     */
    public static function moreFilterComponents(array $filters, array $keys): array
    {
        return ProductCatalogListToolbar::moreFilterComponents($filters, $keys);
    }

    public static function configureMoreFiltersTrigger(\Filament\Actions\Action $action): \Filament\Actions\Action
    {
        return ProductCatalogListToolbar::configureMoreFiltersTrigger($action);
    }

    /**
     * @param  list<string>  $moreFilterKeys
     */
    public static function hasMoreFilters(array $moreFilterKeys): bool
    {
        return ProductCatalogListToolbar::hasMoreFilters($moreFilterKeys);
    }
}
