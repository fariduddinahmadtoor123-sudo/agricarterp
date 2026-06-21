<?php

namespace App\Filament\Pages\Approvals\Purchasing;

use App\Filament\Pages\Concerns\InteractsWithApprovalPage;
use Filament\Pages\Page;

class Quotations extends Page
{
    use InteractsWithApprovalPage;

    protected static ?string $slug = 'approvals/purchasing/quotations';

    protected static bool $shouldRegisterNavigation = false;

    public static function categoryKey(): string
    {
        return 'purchasing';
    }

    public static function typeKey(): ?string
    {
        return 'quotations';
    }
}

