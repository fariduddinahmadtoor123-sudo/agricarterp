<?php

namespace App\Filament\Pages\Approvals;

use App\Filament\Pages\Concerns\InteractsWithApprovalPage;
use Filament\Pages\Page;

class Overview extends Page
{
    use InteractsWithApprovalPage;

    protected static ?string $slug = 'approvals/overview';

    protected static bool $shouldRegisterNavigation = false;

    public static function categoryKey(): string
    {
        return 'overview';
    }

    public static function typeKey(): ?string
    {
        return null;
    }
}
