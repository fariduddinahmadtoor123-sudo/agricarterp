<?php

namespace App\Services\PurchasingInventory;

use Illuminate\Support\Str;

class PurchaseQuotationLineBuilder
{
    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public function fromProduct(array $product): array
    {
        return [
            'line_id' => (string) Str::uuid(),
            'product_id' => (int) $product['id'],
            'thumbnail_url' => $product['thumbnail_url'] ?? null,
            'barcode' => (string) ($product['barcode'] ?? ''),
            'sku' => (string) ($product['sku'] ?? ''),
            'name_en' => (string) ($product['name_en'] ?? ''),
            'name_ur' => (string) ($product['name_ur'] ?? ''),
            'required_qty' => '',
            'unit_price' => '',
        ];
    }

    public static function displayName(array $row, string $nameLang): string
    {
        return PurchasePlanningLineBuilder::displayName($row, $nameLang);
    }

    public static function lineTotal(array $row): float
    {
        $qty = (float) str_replace(',', '', (string) ($row['required_qty'] ?? '0'));
        $price = (float) str_replace(',', '', (string) ($row['unit_price'] ?? '0'));

        if ($qty <= 0 || $price < 0) {
            return 0.0;
        }

        return round($qty * $price, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function grandTotal(array $rows): float
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
}
