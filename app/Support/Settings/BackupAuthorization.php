<?php

namespace App\Support\Settings;

use App\Support\Authorization\PermissionChecker;

class BackupAuthorization
{
    public static function canView(): bool
    {
        return PermissionChecker::can('settings', 'view');
    }

    public static function canCreate(): bool
    {
        return PermissionChecker::can('settings', 'edit');
    }

    public static function canDownload(): bool
    {
        return static::canCreate();
    }

    public static function canDelete(): bool
    {
        return PermissionChecker::isSuperAdmin() || static::canCreate();
    }

    public static function canRestore(): bool
    {
        return PermissionChecker::isSuperAdmin();
    }

    public static function canManageSchedules(): bool
    {
        return PermissionChecker::isSuperAdmin();
    }
}
