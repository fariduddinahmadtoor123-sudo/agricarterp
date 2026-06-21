<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class SystemBackups extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'settings/system-backups';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'settings';
    }

    public static function submenuKey(): string
    {
        return 'system-backups';
    }
}
