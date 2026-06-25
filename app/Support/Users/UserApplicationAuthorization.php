<?php

namespace App\Support\Users;

use App\Support\Authorization\PermissionChecker;

class UserApplicationAuthorization
{
    public static function canView(): bool
    {
        return PermissionChecker::can('approvals', 'view');
    }

    public static function canApprove(): bool
    {
        return PermissionChecker::can('approvals', 'approve');
    }

    public static function canReject(): bool
    {
        return PermissionChecker::can('approvals', 'reject');
    }
}
