<?php

namespace App\Support\ProductCatalog;

use App\Support\Authorization\PermissionChecker;

class CategoryAuthorization
{
    public static function canView(): bool
    {
        return PermissionChecker::can('product-catalog', 'view');
    }

    public static function canCreate(): bool
    {
        return PermissionChecker::can('product-catalog', 'create');
    }

    public static function canEdit(): bool
    {
        return PermissionChecker::can('product-catalog', 'edit');
    }

    public static function canMove(): bool
    {
        return static::canEdit();
    }

    public static function canArchive(): bool
    {
        return PermissionChecker::can('product-catalog', 'archive');
    }

    public static function canRestore(): bool
    {
        return PermissionChecker::can('product-catalog', 'restore');
    }
}
