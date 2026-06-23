<?php

namespace App\Filament\Pages\Logistics;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class Overview extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'logistics/overview';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'logistics';
    }

    public static function submenuKey(): string
    {
        return 'overview';
    }
}
