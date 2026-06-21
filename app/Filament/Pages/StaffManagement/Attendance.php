<?php

namespace App\Filament\Pages\StaffManagement;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class Attendance extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'staff-management/attendance';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'staff-management';
    }

    public static function submenuKey(): string
    {
        return 'attendance';
    }
}
