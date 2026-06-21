<?php

namespace App\Filament\Pages\ProductCatalog;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class Overview extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'product-catalog/overview';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'product-catalog';
    }

    public static function submenuKey(): string
    {
        return 'overview';
    }
}
