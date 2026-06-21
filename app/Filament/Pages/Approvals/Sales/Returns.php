<?php

namespace App\Filament\Pages\Approvals\Sales;

use App\Filament\Pages\Concerns\InteractsWithApprovalPage;
use Filament\Pages\Page;

class Returns extends Page
{
    use InteractsWithApprovalPage;

    protected static ?string $slug = 'approvals/sales/returns';

    protected static bool $shouldRegisterNavigation = false;

    public static function categoryKey(): string
    {
        return 'sales';
    }

    public static function typeKey(): ?string
    {
        return 'returns';
    }
}

