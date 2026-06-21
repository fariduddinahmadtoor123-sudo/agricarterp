<?php

namespace App\Filament\Pages\Marketplace;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class ChannelConnections extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'marketplace/channel-connections';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'marketplace';
    }

    public static function submenuKey(): string
    {
        return 'channel-connections';
    }
}
