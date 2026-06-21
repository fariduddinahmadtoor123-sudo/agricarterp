<?php

namespace App\Filament\Pages\PurchasingInventory;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class PurchaseQuotations extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'purchasing-inventory/purchase-quotations';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'purchasing-inventory';
    }

    public static function submenuKey(): string
    {
        return 'purchase-quotations';
    }
}
