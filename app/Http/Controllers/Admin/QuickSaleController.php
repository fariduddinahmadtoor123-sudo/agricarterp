<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Pages\SalesPos\PosSaleWorksheet;
use App\Support\SalesPos\PosSaleRepository;
use Illuminate\Http\RedirectResponse;

class QuickSaleController
{
    public function __invoke(): RedirectResponse
    {
        $sheet = app(PosSaleRepository::class)->create();

        return redirect(PosSaleWorksheet::getUrl(['saleId' => $sheet['id']]));
    }
}
