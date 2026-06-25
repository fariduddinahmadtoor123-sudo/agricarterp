<?php

namespace App\Support\PurchasingInventory;

use App\Support\Authorization\PermissionChecker;

trait EnforcesPurchasingInventoryPermissions
{
    protected function authorizePurchasingCreate(): void
    {
        PermissionChecker::authorize('purchasing-inventory', 'create');
    }

    protected function authorizePurchasingEdit(): void
    {
        PermissionChecker::authorize('purchasing-inventory', 'edit');
    }

    protected function authorizePurchasingDelete(): void
    {
        PermissionChecker::authorize('purchasing-inventory', 'edit');
    }
}
