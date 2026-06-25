<?php

namespace App\Support\Settings;

use App\Models\User;

class PrintingSettingAuthorization
{
    public static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function canView(): bool
    {
        return static::user() !== null;
    }

    public static function canCreate(): bool
    {
        return static::canView();
    }

    public static function canEdit(): bool
    {
        return static::canView();
    }
}
