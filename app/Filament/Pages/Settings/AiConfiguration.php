<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class AiConfiguration extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'settings/ai-configuration';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'settings';
    }

    public static function submenuKey(): string
    {
        return 'ai-configuration';
    }
}
