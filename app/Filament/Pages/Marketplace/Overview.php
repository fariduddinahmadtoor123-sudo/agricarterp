<?php

namespace App\Filament\Pages\Marketplace;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class Overview extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'marketplace/overview';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'marketplace';
    }

    public static function submenuKey(): string
    {
        return 'overview';
    }
}
