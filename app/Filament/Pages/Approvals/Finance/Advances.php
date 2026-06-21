<?php

namespace App\Filament\Pages\Approvals\Finance;

use App\Filament\Pages\Concerns\InteractsWithApprovalPage;
use Filament\Pages\Page;

class Advances extends Page
{
    use InteractsWithApprovalPage;

    protected static ?string $slug = 'approvals/finance/advances';

    protected static bool $shouldRegisterNavigation = false;

    public static function categoryKey(): string
    {
        return 'finance';
    }

    public static function typeKey(): ?string
    {
        return 'advances';
    }
}

