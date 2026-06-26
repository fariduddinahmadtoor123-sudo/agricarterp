<?php

namespace App\Support\Settings;

use App\Support\Authorization\PermissionChecker;

class SettingsAuthorization
{
    public static function canView(): bool
    {
        return PermissionChecker::can('settings', 'view');
    }

    public static function canEdit(): bool
    {
        return PermissionChecker::can('settings', 'edit');
    }

    public static function canCreate(): bool
    {
        return static::canEdit();
    }
}
