<?php

namespace App\Filament\Pages\SalesPos;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class OnlineOrders extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'sales-pos/online-orders';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'sales-pos';
    }

    public static function submenuKey(): string
    {
        return 'online-orders';
    }
}
