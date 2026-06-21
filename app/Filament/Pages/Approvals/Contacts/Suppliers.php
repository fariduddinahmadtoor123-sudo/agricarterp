<?php

namespace App\Filament\Pages\Approvals\Contacts;

use App\Filament\Pages\Concerns\InteractsWithApprovalPage;
use Filament\Pages\Page;

class Suppliers extends Page
{
    use InteractsWithApprovalPage;

    protected static ?string $slug = 'approvals/contacts/suppliers';

    protected static bool $shouldRegisterNavigation = false;

    public static function categoryKey(): string
    {
        return 'contacts';
    }

    public static function typeKey(): ?string
    {
        return 'suppliers';
    }
}

