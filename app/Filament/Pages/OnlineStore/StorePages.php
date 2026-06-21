<?php

namespace App\Filament\Pages\OnlineStore;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class StorePages extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'online-store/pages';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'online-store';
    }

    public static function submenuKey(): string
    {
        return 'pages';
    }
}
