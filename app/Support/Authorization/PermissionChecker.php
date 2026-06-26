<?php

namespace App\Support\Authorization;

use App\Models\User;

class PermissionChecker
{
    public static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function can(string $module, string $action): bool
    {
        $user = static::user();

        return $user !== null && $user->hasPermission($module, $action);
    }

    public static function isSuperAdmin(): bool
    {
        return static::user()?->isSuperAdmin() ?? false;
    }

    public static function authorize(string $module, string $action): void
    {
        if (static::user() === null) {
            return;
        }

        abort_unless(static::can($module, $action), 403);
    }

    public static function authorizeAbility(callable $allowed): void
    {
        if (static::user() === null) {
            return;
        }

        abort_unless($allowed(), 403);
    }
}
