<?php

namespace App\Support\Users;

use App\Support\Authorization\PermissionChecker;

class RoleAuthorization
{
    public static function canView(): bool
    {
        return PermissionChecker::can('roles', 'view');
    }

    public static function canCreate(): bool
    {
        return PermissionChecker::can('roles', 'create');
    }

    public static function canEdit(): bool
    {
        return PermissionChecker::can('roles', 'edit');
    }

    public static function canDelete(): bool
    {
        return PermissionChecker::can('roles', 'delete');
    }
}
