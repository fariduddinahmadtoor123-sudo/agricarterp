<?php

namespace App\Services\PurchasingInventory;

use Illuminate\Support\Str;

class ReOrderLineBuilder
{
    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public function fromProduct(array $product, float $onHand = 0): array
    {
        $target = (float) ($product['required_quantity'] ?? 0);
        $requiredQty = $this->suggestedOrderQty($target, $onHand, (float) ($product['alert_quantity'] ?? 0));

        return [
            'line_id' => (string) Str::uuid(),
            'product_id' => (int) $product['id'],
            'thumbnail_url' => $product['thumbnail_url'] ?? null,
            'barcode' => (string) ($product['barcode'] ?? ''),
            'sku' => (string) ($product['sku'] ?? ''),
            'name_en' => (string) ($product['name_en'] ?? ''),
            'name_ur' => (string) ($product['name_ur'] ?? ''),
            'on_hand' => PurchaseLineBuilder::formatQuantity($onHand),
            'alert_qty' => PurchaseLineBuilder::formatQuantity($product['alert_quantity'] ?? ''),
            'required_qty' => PurchaseLineBuilder::formatQuantity($requiredQty),
            'previous_rate' => $this->previousRateForProduct((int) $product['id']),
        ];
    }

    public function suggestedOrderQty(float $targetQty, float $onHand, float $alertQty): float
    {
        if ($targetQty > $onHand) {
            return max(1, $targetQty - $onHand);
        }

        if ($alertQty > 0 && $onHand <= $alertQty) {
            return max(1, $alertQty - $onHand);
        }

        return max(1, $targetQty > 0 ? $targetQty : 1);
    }

    public function previousRateForProduct(int $productId): string
    {
        $rates = session('reorder_previous_rates', []);

        if (! is_array($rates) || ! isset($rates[$productId])) {
            return '';
        }

        return PurchaseLineBuilder::formatAmount((float) $rates[$productId]);
    }

    public static function displayName(array $row, string $nameLang): string
    {
        return PurchasePlanningLineBuilder::displayName($row, $nameLang);
    }
}
