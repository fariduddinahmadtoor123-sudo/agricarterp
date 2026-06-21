<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class GeneralSettings extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'settings/general-settings';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'settings';
    }

    public static function submenuKey(): string
    {
        return 'general-settings';
    }
}
