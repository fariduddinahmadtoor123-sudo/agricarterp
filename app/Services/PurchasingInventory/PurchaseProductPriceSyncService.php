<?php

namespace App\Services\PurchasingInventory;

use App\Models\PurchasingInventory\ProductStorePrice;
use App\Services\Settings\PurchasePricingSettingResolver;
use Illuminate\Support\Facades\DB;

class PurchaseProductPriceSyncService
{
    public function __construct(
        protected PurchasePricingSettingResolver $pricingSettings,
    ) {}

    /**
     * @param  array<string, mixed>  $sheet
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncIfEnabled(array $sheet, array $rows): void
    {
        if (! $this->pricingSettings->shouldUpdateProductPricesFromPurchases()) {
            return;
        }

        if ((string) ($sheet['status'] ?? '') !== 'saved') {
            return;
        }

        $storeKey = (string) ($sheet['store_key'] ?? config('purchasing-inventory.demo_default_store', 'main'));
        $sheetId = (string) ($sheet['id'] ?? '');

        DB::transaction(function () use ($rows, $storeKey, $sheetId): void {
            foreach ($rows as $row) {
                $this->syncRow($storeKey, $row, $sheetId);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function syncRow(string $storeKey, array $row, string $sheetId): void
    {
        $productId = (int) ($row['product_id'] ?? 0);

        if ($productId <= 0) {
            return;
        }

        $row = PurchaseLineBuilder::applyTierRates($row, syncMarkupsFromSettings: true);

        $purchaseRate = PurchaseLineBuilder::numeric($row['purchase_rate'] ?? '');

        if ($purchaseRate <= 0) {
            return;
        }

        ProductStorePrice::query()->updateOrCreate(
            [
                'product_id' => $productId,
                'store_key' => $storeKey,
            ],
            [
                'purchase_rate' => round($purchaseRate, 4),
                'landing_cost' => $this->nullableAmount($row['landing_cost'] ?? null),
                'sale_rate' => $this->nullableAmount($row['sale_rate'] ?? null),
                'wholesale_rate' => $this->nullableAmount($row['wholesale_rate'] ?? null),
                'super_wholesale_rate' => $this->nullableAmount($row['super_wholesale_rate'] ?? null),
                'distributor_rate' => $this->nullableAmount($row['distributor_rate'] ?? null),
                'source_purchase_sheet_id' => filled($sheetId) ? $sheetId : null,
            ],
        );
    }

    protected function nullableAmount(mixed $value): ?float
    {
        $amount = PurchaseLineBuilder::numeric($value);

        return $amount > 0 ? round($amount, 4) : null;
    }
}
