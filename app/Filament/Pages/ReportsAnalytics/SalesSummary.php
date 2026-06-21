<?php

namespace App\Filament\Pages\ReportsAnalytics;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class SalesSummary extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'reports-analytics/sales-summary';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'reports-analytics';
    }

    public static function submenuKey(): string
    {
        return 'sales-summary';
    }
}
