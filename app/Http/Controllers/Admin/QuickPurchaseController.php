<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Pages\PurchasingInventory\PurchaseWorksheet;
use App\Support\PurchasingInventory\PurchaseSheetRepository;
use App\Support\PurchasingInventory\PurchasingInventoryAuthorization;
use Illuminate\Http\RedirectResponse;

class QuickPurchaseController
{
    public function __invoke(): RedirectResponse
    {
        abort_unless(PurchasingInventoryAuthorization::canCreate(), 403);

        $sheet = app(PurchaseSheetRepository::class)->create();

        return redirect(PurchaseWorksheet::getUrl(['purchaseId' => $sheet['id']]));
    }
}
