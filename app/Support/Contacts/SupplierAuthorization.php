<?php

namespace App\Support\Contacts;

use App\Support\Authorization\PermissionChecker;

class SupplierAuthorization
{
    public static function canView(): bool
    {
        return PermissionChecker::can('contacts', 'view');
    }

    public static function canCreate(): bool
    {
        return PermissionChecker::can('contacts', 'create');
    }

    public static function canEdit(): bool
    {
        return PermissionChecker::can('contacts', 'edit');
    }

    public static function canInactivate(): bool
    {
        return static::canEdit();
    }

    public static function canDelete(): bool
    {
        return PermissionChecker::can('contacts', 'delete');
    }

    public static function canRestore(): bool
    {
        return PermissionChecker::can('contacts', 'restore');
    }
}
