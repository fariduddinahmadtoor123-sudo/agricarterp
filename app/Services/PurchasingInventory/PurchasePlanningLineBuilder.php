<?php

namespace App\Services\PurchasingInventory;

use Illuminate\Support\Str;

class PurchasePlanningLineBuilder
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
            'stock' => (string) ($product['stock'] ?? ''),
            'required_qty' => '',
            'low_stock' => (string) ($product['low_stock'] ?? ''),
            'purchase_price' => '',
            'landing_cost' => '',
            'sale_price' => '',
        ];
    }

    public static function displayName(array $row, string $nameLang): string
    {
        $english = trim((string) ($row['name_en'] ?? ''));
        $urdu = trim((string) ($row['name_ur'] ?? ''));

        return match ($nameLang) {
            'en' => $english,
            'ur' => $urdu,
            default => match (true) {
                $english !== '' && $urdu !== '' => $english . ' / ' . $urdu,
                $english !== '' => $english,
                default => $urdu,
            },
        };
    }
}
