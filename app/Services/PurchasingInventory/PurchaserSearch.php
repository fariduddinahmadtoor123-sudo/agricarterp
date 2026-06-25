<?php

namespace App\Services\PurchasingInventory;

use App\Models\PurchasingInventory\Purchaser;

class PurchaserSearch
{
    /**
     * @return list<array{id: int, name: string, mobile: string|null, code: string|null}>
     */
    public function activePurchasers(): array
    {
        return Purchaser::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'mobile', 'code'])
            ->map(fn (Purchaser $purchaser): array => [
                'id' => (int) $purchaser->id,
                'name' => (string) $purchaser->name,
                'mobile' => $purchaser->mobile,
                'code' => $purchaser->code,
            ])
            ->all();
    }

    public function findById(int $purchaserId): ?array
    {
        $purchaser = Purchaser::query()->active()->find($purchaserId);

        if ($purchaser === null) {
            return null;
        }

        return [
            'id' => (int) $purchaser->id,
            'name' => (string) $purchaser->name,
            'mobile' => $purchaser->mobile,
            'code' => $purchaser->code,
        ];
    }
}
