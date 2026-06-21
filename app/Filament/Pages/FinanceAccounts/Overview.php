<?php

namespace App\Filament\Pages\FinanceAccounts;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class Overview extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'finance-accounts/overview';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'finance-accounts';
    }

    public static function submenuKey(): string
    {
        return 'overview';
    }
}
