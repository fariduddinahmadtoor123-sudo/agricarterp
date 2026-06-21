<?php

namespace App\Filament\Pages\SalesPos;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class Overview extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'sales-pos/overview';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'sales-pos';
    }

    public static function submenuKey(): string
    {
        return 'overview';
    }
}
