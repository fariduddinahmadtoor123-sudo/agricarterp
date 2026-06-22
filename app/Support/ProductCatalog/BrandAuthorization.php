<?php

namespace App\Support\ProductCatalog;

use App\Models\User;

class BrandAuthorization
{
    public static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function isSuperAdmin(): bool
    {
        return static::user()?->isSuperAdmin() ?? false;
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

    public static function canArchive(): bool
    {
        return static::isSuperAdmin();
    }

    public static function canRestore(): bool
    {
        return static::isSuperAdmin();
    }
}
