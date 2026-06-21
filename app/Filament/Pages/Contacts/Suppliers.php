<?php

namespace App\Filament\Pages\Contacts;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use Filament\Pages\Page;

class Suppliers extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'contacts/suppliers';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'contacts';
    }

    public static function submenuKey(): string
    {
        return 'suppliers';
    }
}
