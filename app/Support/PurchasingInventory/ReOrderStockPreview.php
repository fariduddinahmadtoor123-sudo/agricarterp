<?php

namespace App\Support\PurchasingInventory;

use App\Models\Product;

/**
 * Session-backed on-hand preview until inventory integration.
 */
class ReOrderStockPreview
{
    public const SESSION_KEY = 'reorder_stock_preview';

    public function onHand(int $productId): float
    {
        $map = session(self::SESSION_KEY, []);

        if (isset($map[$productId]) && is_numeric($map[$productId])) {
            return max(0, (float) $map[$productId]);
        }

        return $this->defaultOnHand($productId);
    }

    public function setOnHand(int $productId, float $quantity): void
    {
        $map = session(self::SESSION_KEY, []);
        $map[$productId] = max(0, $quantity);
        session([self::SESSION_KEY => $map]);
    }

    public function receiveStock(int $productId, float $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $this->setOnHand($productId, $this->onHand($productId) + $quantity);
    }

    public function stockStatus(int $productId): string
    {
        $product = Product::query()->find($productId);

        if ($product === null) {
            return 'ok';
        }

        $onHand = $this->onHand($productId);
        $alert = (float) $product->alert_quantity;
        $required = (float) $product->required_quantity;

        if ($alert <= 0 && $required <= 0) {
            return 'ok';
        }

        if ($onHand <= 0) {
            return 'out';
        }

        if ($alert > 0 && $onHand <= $alert) {
            return 'low';
        }

        return 'ok';
    }

    protected function defaultOnHand(int $productId): float
    {
        $product = Product::query()->find($productId);

        if ($product === null) {
            return 0.0;
        }

        $alert = (float) $product->alert_quantity;
        $required = (float) $product->required_quantity;

        if ($alert > 0) {
            return max(0, floor($alert / 2));
        }

        if ($required > 0) {
            return 0.0;
        }

        return 999.0;
    }
}
