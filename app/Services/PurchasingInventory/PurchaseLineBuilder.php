<?php

namespace App\Services\PurchasingInventory;

use Illuminate\Support\Str;

class PurchaseLineBuilder
{
    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public function fromProduct(array $product): array
    {
        $tiers = config('purchasing-inventory.purchase_pricing_tiers', []);

        $row = [
            'line_id' => (string) Str::uuid(),
            'product_id' => (int) $product['id'],
            'thumbnail_url' => $product['thumbnail_url'] ?? null,
            'barcode' => (string) ($product['barcode'] ?? ''),
            'sku' => (string) ($product['sku'] ?? ''),
            'name_en' => (string) ($product['name_en'] ?? ''),
            'name_ur' => (string) ($product['name_ur'] ?? ''),
            'required_qty' => self::formatQuantity($product['required_quantity'] ?? ''),
            'alert_qty' => self::formatQuantity($product['alert_quantity'] ?? $product['low_stock'] ?? ''),
            'purchase_qty' => '',
            'received_qty' => '',
            'damaged_qty' => '',
            'previous_rate' => '',
            'purchase_rate' => '',
            'landing_cost' => '',
            'sale_rate' => '',
            'wholesale_pct' => (string) ($tiers['wholesale']['default_pct'] ?? '5'),
            'wholesale_rate' => '',
            'super_wholesale_pct' => (string) ($tiers['super_wholesale']['default_pct'] ?? '8'),
            'super_wholesale_rate' => '',
            'distributor_pct' => (string) ($tiers['distributor']['default_pct'] ?? '12'),
            'distributor_rate' => '',
        ];

        return self::applyTierRates($row);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function applyTierRates(array $row): array
    {
        $purchaseRate = self::numeric($row['purchase_rate'] ?? '');

        if ($purchaseRate <= 0) {
            return $row;
        }

        foreach (['wholesale', 'super_wholesale', 'distributor'] as $tier) {
            $pct = self::numeric($row[$tier . '_pct'] ?? '');

            if ($pct > 0) {
                $row[$tier . '_rate'] = self::formatAmount(self::tierRateFromPurchase($purchaseRate, $pct));
            }
        }

        return $row;
    }

    public static function tierRateFromPurchase(float $purchaseRate, float $markupPct): float
    {
        if ($purchaseRate <= 0) {
            return 0.0;
        }

        return round($purchaseRate * (100 + $markupPct) / 100, 2);
    }

    public static function displayName(array $row, string $nameLang): string
    {
        return PurchasePlanningLineBuilder::displayName($row, $nameLang);
    }

    public static function lineTotal(array $row): float
    {
        $qty = self::numeric($row['purchase_qty'] ?? '0');
        $rate = self::numeric($row['purchase_rate'] ?? '0');

        if ($qty <= 0 || $rate < 0) {
            return 0.0;
        }

        return round($qty * $rate, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function invoiceTotal(array $rows): float
    {
        return round(collect($rows)->sum(fn (array $row): float => self::lineTotal($row)), 2);
    }

    public static function formatAmount(float $amount): string
    {
        if ($amount === 0.0) {
            return '';
        }

        return number_format($amount, 2, '.', ',');
    }

    public static function formatQuantity(string | float | int | null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) str_replace(',', '', (string) $value);

        if ($number == 0.0) {
            return '';
        }

        if (abs($number - round($number)) < 0.00001) {
            return (string) (int) round($number);
        }

        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function normalizeRowQuantities(array $row): array
    {
        foreach (['required_qty', 'alert_qty', 'purchase_qty', 'received_qty', 'damaged_qty'] as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = self::formatQuantity($row[$field]);
            }
        }

        return $row;
    }

    public static function numeric(string | float | int | null $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) str_replace(',', '', (string) $value);
    }
}
