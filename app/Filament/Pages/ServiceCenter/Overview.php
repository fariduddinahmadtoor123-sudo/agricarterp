<?php

namespace App\Filament\Pages\ServiceCenter;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class Overview extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'service-center/overview';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'service-center';
    }

    public static function submenuKey(): string
    {
        return 'overview';
    }
}
