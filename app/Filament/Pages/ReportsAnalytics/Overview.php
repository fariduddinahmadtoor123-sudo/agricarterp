<?php

namespace App\Filament\Pages\ReportsAnalytics;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class Overview extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'reports-analytics/overview';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'reports-analytics';
    }

    public static function submenuKey(): string
    {
        return 'overview';
    }
}
