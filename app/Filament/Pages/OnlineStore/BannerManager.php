<?php

namespace App\Filament\Pages\OnlineStore;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class BannerManager extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'online-store/banner-manager';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'online-store';
    }

    public static function submenuKey(): string
    {
        return 'banner-manager';
    }
}
