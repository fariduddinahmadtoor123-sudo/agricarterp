<?php

namespace App\Support\Settings;

use App\Support\Authorization\PermissionChecker;

class AiSettingAuthorization
{
    public static function canView(): bool
    {
        return PermissionChecker::can('settings', 'view');
    }

    public static function canEdit(): bool
    {
        return PermissionChecker::can('settings', 'edit');
    }

    public static function canRunEnrichment(): bool
    {
        return static::canEdit()
            || PermissionChecker::can('product-catalog', 'edit');
    }
}
