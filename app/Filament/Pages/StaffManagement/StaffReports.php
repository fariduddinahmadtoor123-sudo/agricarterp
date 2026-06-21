<?php

namespace App\Filament\Pages\StaffManagement;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class StaffReports extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'staff-management/staff-reports';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'staff-management';
    }

    public static function submenuKey(): string
    {
        return 'staff-reports';
    }
}
