<?php

namespace App\Services\SalesPos;

use App\Models\CustomerAccountEntry;
use App\Models\SalesPos\PosSale;
use App\Models\SalesPos\SalesReturn;
use App\Support\SalesPos\PosSaleRepository;

class CustomerAccountService
{
    public function creditFromSaleReturn(
        int $customerId,
        float $amount,
        string $returnId,
        string $description = '',
    ): void {
        if ($customerId <= 0 || $amount <= 0) {
            return;
        }

        CustomerAccountEntry::query()->create([
            'customer_id' => $customerId,
            'entry_type' => CustomerAccountEntry::TYPE_SALE_RETURN_CREDIT,
            'amount' => round($amount, 2),
            'reference_type' => 'sales_return',
            'reference_id' => $returnId,
            'description' => $description !== '' ? $description : 'Sales return credit',
            'created_by' => auth()->id(),
        ]);
    }
}
