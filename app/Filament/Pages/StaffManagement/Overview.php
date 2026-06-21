<?php

namespace App\Filament\Pages\StaffManagement;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class Overview extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'staff-management/overview';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'staff-management';
    }

    public static function submenuKey(): string
    {
        return 'overview';
    }
}
