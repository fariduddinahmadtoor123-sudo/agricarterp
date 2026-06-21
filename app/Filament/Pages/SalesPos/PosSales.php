<?php

namespace App\Filament\Pages\SalesPos;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class PosSales extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'sales-pos/pos-sales';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'sales-pos';
    }

    public static function submenuKey(): string
    {
        return 'pos-sales';
    }
}
