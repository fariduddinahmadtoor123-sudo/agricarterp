<?php

namespace App\Filament\Pages\PurchasingInventory;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class Overview extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'purchasing-inventory/overview';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'purchasing-inventory';
    }

    public static function submenuKey(): string
    {
        return 'overview';
    }
}
