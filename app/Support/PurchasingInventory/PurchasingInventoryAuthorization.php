<?php

namespace App\Support\PurchasingInventory;

use App\Support\Authorization\PermissionChecker;

class PurchasingInventoryAuthorization
{
    public static function canView(): bool
    {
        return PermissionChecker::can('purchasing-inventory', 'view');
    }

    public static function canCreate(): bool
    {
        return PermissionChecker::can('purchasing-inventory', 'create');
    }

    public static function canEdit(): bool
    {
        return PermissionChecker::can('purchasing-inventory', 'edit');
    }

    public static function canDelete(): bool
    {
        return static::canEdit();
    }
}
