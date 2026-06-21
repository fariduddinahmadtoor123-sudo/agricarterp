<?php

namespace App\Filament\Pages\Approvals\Staff;

use App\Filament\Pages\Concerns\InteractsWithApprovalPage;
use Filament\Pages\Page;

class Users extends Page
{
    use InteractsWithApprovalPage;

    protected static ?string $slug = 'approvals/staff/users';

    protected static bool $shouldRegisterNavigation = false;

    public static function categoryKey(): string
    {
        return 'staff';
    }

    public static function typeKey(): ?string
    {
        return 'users';
    }
}

