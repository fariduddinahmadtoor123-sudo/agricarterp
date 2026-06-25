<?php

namespace App\Support\Users;

use App\Support\Authorization\PermissionChecker;

class UserAuthorization
{
    public static function canView(): bool
    {
        return PermissionChecker::can('users', 'view');
    }

    public static function canCreate(): bool
    {
        return PermissionChecker::can('users', 'create');
    }

    public static function canEdit(): bool
    {
        return PermissionChecker::can('users', 'edit');
    }

    public static function canDeactivate(): bool
    {
        return PermissionChecker::can('users', 'deactivate');
    }
}
