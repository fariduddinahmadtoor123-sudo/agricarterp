<?php

namespace App\Support\Settings;

class PrintingSettingAuthorization
{
    public static function canView(): bool
    {
        return SettingsAuthorization::canView();
    }

    public static function canCreate(): bool
    {
        return SettingsAuthorization::canCreate();
    }

    public static function canEdit(): bool
    {
        return SettingsAuthorization::canEdit();
    }
}
