<?php

namespace App\Filament\Pages\Approvals\ProductCatalog;

use App\Filament\Pages\Concerns\InteractsWithApprovalPage;
use Filament\Pages\Page;

class Categories extends Page
{
    use InteractsWithApprovalPage;

    protected static ?string $slug = 'approvals/product-catalog/categories';

    protected static bool $shouldRegisterNavigation = false;

    public static function categoryKey(): string
    {
        return 'product-catalog';
    }

    public static function typeKey(): ?string
    {
        return 'categories';
    }
}

