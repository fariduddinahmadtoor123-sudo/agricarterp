<?php

namespace App\Filament\Pages\ReportsAnalytics;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class CustomerLedger extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'reports-analytics/customer-ledger';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'reports-analytics';
    }

    public static function submenuKey(): string
    {
        return 'customer-ledger';
    }
}
