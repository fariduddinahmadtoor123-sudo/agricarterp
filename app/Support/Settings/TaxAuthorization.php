<?php

namespace App\Support\Settings;

class TaxAuthorization
{
    public static function canView(): bool
    {
        return SettingsAuthorization::canView();
    }

    public static function canCreate(): bool
    {
        return SettingsAuthorization::canEdit();
    }

    public static function canEdit(): bool
    {
        return SettingsAuthorization::canEdit();
    }

    public static function canDelete(): bool
    {
        return SettingsAuthorization::canEdit();
    }
}
