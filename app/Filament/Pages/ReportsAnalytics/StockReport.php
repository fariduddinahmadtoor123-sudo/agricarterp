<?php

namespace App\Filament\Pages\ReportsAnalytics;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class StockReport extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'reports-analytics/stock-report';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'reports-analytics';
    }

    public static function submenuKey(): string
    {
        return 'stock-report';
    }
}
