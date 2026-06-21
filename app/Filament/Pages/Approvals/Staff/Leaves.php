<?php

namespace App\Filament\Pages\Approvals\Staff;

use App\Filament\Pages\Concerns\InteractsWithApprovalPage;
use Filament\Pages\Page;

class Leaves extends Page
{
    use InteractsWithApprovalPage;

    protected static ?string $slug = 'approvals/staff/leaves';

    protected static bool $shouldRegisterNavigation = false;

    public static function categoryKey(): string
    {
        return 'staff';
    }

    public static function typeKey(): ?string
    {
        return 'leaves';
    }
}

