<?php

namespace App\Services\PurchasingInventory;

use App\Models\Product;
use App\Models\PurchasingInventory\InventoryBalance;
use App\Models\PurchasingInventory\InventoryMovement;
use App\Models\PurchasingInventory\OpeningStockEntry;
use App\Models\PurchasingInventory\ProductStoreCost;
use App\Models\PurchasingInventory\PurchaseRateAudit;
use App\Models\PurchasingInventory\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function defaultStoreKey(): string
    {
        return (string) config('purchasing-inventory.demo_default_store', 'main');
    }

    public function onHand(int $productId, ?string $storeKey = null): float
    {
        $storeKey ??= $this->defaultStoreKey();

        $balance = InventoryBalance::query()
            ->where('product_id', $productId)
            ->where('store_key', $storeKey)
            ->value('on_hand');

        return max(0, (float) ($balance ?? 0));
    }

    public function stockStatus(int $productId, ?string $storeKey = null): string
    {
        $product = Product::query()->find($productId);

        if ($product === null) {
            return 'ok';
        }

        $onHand = $this->onHand($productId, $storeKey);
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

    /**
     * @param  array<string, mixed>  $sheet
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows: list<array<string, mixed>>, goods_receipt_status: string}
     */
    public function receivePurchaseGoods(array $sheet, array $rows): array
    {
        $storeKey = (string) ($sheet['store_key'] ?? $this->defaultStoreKey());
        $referenceId = (string) ($sheet['id'] ?? '');
        $updatedRows = [];

        DB::transaction(function () use ($rows, $storeKey, $referenceId, &$updatedRows): void {
            foreach ($rows as $row) {
                $productId = (int) ($row['product_id'] ?? 0);

                if ($productId <= 0) {
                    $updatedRows[] = $row;

                    continue;
                }

                $receivedQty = PurchaseLineBuilder::numeric($row['received_qty'] ?? '');
                $damagedQty = PurchaseLineBuilder::numeric($row['damaged_qty'] ?? '');
                $purchaseRate = PurchaseLineBuilder::numeric($row['purchase_rate'] ?? '');

                if ($damagedQty > $receivedQty) {
                    throw ValidationException::withMessages([
                        'rows' => 'Damaged quantity cannot exceed received quantity.',
                    ]);
                }

                $goodQty = max(0, $receivedQty - $damagedQty);
                $previouslyReceived = PurchaseLineBuilder::numeric($row['_gr_applied_qty'] ?? '');
                $incrementalQty = max(0, $goodQty - $previouslyReceived);

                if ($incrementalQty > 0) {
                    $this->increaseStock(
                        productId: $productId,
                        storeKey: $storeKey,
                        quantity: $incrementalQty,
                        movementType: 'purchase_receipt',
                        referenceType: 'purchase_sheet',
                        referenceId: $referenceId,
                        notes: $damagedQty > 0 ? 'Damaged qty excluded: ' . PurchaseLineBuilder::formatQuantity($damagedQty) : null,
                    );

                    if ($purchaseRate > 0) {
                        $this->applyPurchaseRateCosts(
                            productId: $productId,
                            storeKey: $storeKey,
                            receivedQty: $incrementalQty,
                            purchaseRate: $purchaseRate,
                            referenceType: 'purchase_sheet',
                            referenceId: $referenceId,
                        );
                    }
                }

                $row['_gr_applied_qty'] = PurchaseLineBuilder::formatQuantity($goodQty);
                $updatedRows[] = $row;
            }
        });

        return [
            'rows' => $updatedRows,
            'goods_receipt_status' => $this->deriveGoodsReceiptStatus($updatedRows),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function deriveGoodsReceiptStatus(array $rows): string
    {
        $linesWithPurchaseQty = collect($rows)
            ->filter(fn (array $row): bool => PurchaseLineBuilder::numeric($row['purchase_qty'] ?? '') > 0);

        if ($linesWithPurchaseQty->isEmpty()) {
            return 'pending';
        }

        $anyReceived = $linesWithPurchaseQty->contains(
            fn (array $row): bool => PurchaseLineBuilder::numeric($row['received_qty'] ?? '') > 0,
        );

        if (! $anyReceived) {
            return 'pending';
        }

        $allReceived = $linesWithPurchaseQty->every(function (array $row): bool {
            $purchaseQty = PurchaseLineBuilder::numeric($row['purchase_qty'] ?? '');
            $receivedQty = PurchaseLineBuilder::numeric($row['received_qty'] ?? '');

            return $receivedQty >= $purchaseQty;
        });

        return $allReceived ? 'received' : 'partial';
    }

    public function applyOpeningStock(string $entryId): void
    {
        DB::transaction(function () use ($entryId): void {
            $entry = OpeningStockEntry::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($entryId);

            if ($entry->status === 'applied') {
                return;
            }

            foreach ($entry->lines as $line) {
                $quantity = (float) $line->quantity;

                if ($quantity <= 0) {
                    continue;
                }

                $this->increaseStock(
                    productId: (int) $line->product_id,
                    storeKey: (string) $entry->store_key,
                    quantity: $quantity,
                    movementType: 'opening_stock',
                    referenceType: 'opening_stock_entry',
                    referenceId: $entry->id,
                );

                $unitCost = (float) $line->unit_cost;

                if ($unitCost > 0) {
                    $this->setAverageAndLastCost(
                        productId: (int) $line->product_id,
                        storeKey: (string) $entry->store_key,
                        averageCost: $unitCost,
                        lastPurchaseCost: $unitCost,
                        referenceType: 'opening_stock_entry',
                        referenceId: $entry->id,
                        purchaseRate: $unitCost,
                        receivedQuantity: $quantity,
                    );
                }
            }

            $entry->update([
                'status' => 'applied',
                'applied_at' => now(),
            ]);
        });
    }

    public function applyStockAdjustment(string $adjustmentId): void
    {
        DB::transaction(function () use ($adjustmentId): void {
            $adjustment = StockAdjustment::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($adjustmentId);

            if ($adjustment->status === 'applied') {
                return;
            }

            foreach ($adjustment->lines as $line) {
                $delta = (float) $line->quantity_delta;

                if ($delta === 0.0) {
                    continue;
                }

                if ($delta > 0) {
                    $this->increaseStock(
                        productId: (int) $line->product_id,
                        storeKey: (string) $adjustment->store_key,
                        quantity: $delta,
                        movementType: 'stock_adjustment_in',
                        referenceType: 'stock_adjustment',
                        referenceId: $adjustment->id,
                        notes: $line->notes,
                    );

                    continue;
                }

                $this->decreaseStock(
                    productId: (int) $line->product_id,
                    storeKey: (string) $adjustment->store_key,
                    quantity: abs($delta),
                    movementType: 'stock_adjustment_out',
                    referenceType: 'stock_adjustment',
                    referenceId: $adjustment->id,
                    notes: $line->notes,
                );
            }

            $adjustment->update([
                'status' => 'applied',
                'applied_at' => now(),
            ]);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function issuePosSale(string $saleId, string $storeKey, array $rows): void
    {
        DB::transaction(function () use ($saleId, $storeKey, $rows): void {
            foreach ($rows as $row) {
                $productId = (int) ($row['product_id'] ?? 0);
                $qty = \App\Services\SalesPos\PosSaleLineBuilder::numeric($row['qty'] ?? '');

                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                $this->decreaseStock(
                    productId: $productId,
                    storeKey: $storeKey,
                    quantity: $qty,
                    movementType: 'pos_sale',
                    referenceType: 'pos_sale',
                    referenceId: $saleId,
                );
            }
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function receivePosSaleReturn(string $returnId, string $storeKey, array $rows): void
    {
        DB::transaction(function () use ($returnId, $storeKey, $rows): void {
            foreach ($rows as $row) {
                $productId = (int) ($row['product_id'] ?? 0);
                $qty = \App\Services\SalesPos\PosSaleLineBuilder::numeric($row['return_qty'] ?? $row['qty'] ?? '');

                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                $this->increaseStock(
                    productId: $productId,
                    storeKey: $storeKey,
                    quantity: $qty,
                    movementType: 'pos_sale_return',
                    referenceType: 'sales_return',
                    referenceId: $returnId,
                );
            }
        });
    }

    protected function increaseStock(
        int $productId,
        string $storeKey,
        float $quantity,
        string $movementType,
        string $referenceType,
        string $referenceId,
        ?string $notes = null,
    ): void {
        if ($quantity <= 0) {
            return;
        }

        $balance = InventoryBalance::query()->firstOrCreate(
            [
                'product_id' => $productId,
                'store_key' => $storeKey,
            ],
            ['on_hand' => 0],
        );

        $balance->on_hand = (float) $balance->on_hand + $quantity;
        $balance->save();

        $this->recordMovement(
            productId: $productId,
            storeKey: $storeKey,
            movementType: $movementType,
            quantity: $quantity,
            balanceAfter: (float) $balance->on_hand,
            referenceType: $referenceType,
            referenceId: $referenceId,
            notes: $notes,
        );
    }

    protected function decreaseStock(
        int $productId,
        string $storeKey,
        float $quantity,
        string $movementType,
        string $referenceType,
        string $referenceId,
        ?string $notes = null,
    ): void {
        if ($quantity <= 0) {
            return;
        }

        $balance = InventoryBalance::query()->firstOrCreate(
            [
                'product_id' => $productId,
                'store_key' => $storeKey,
            ],
            ['on_hand' => 0],
        );

        $newBalance = (float) $balance->on_hand - $quantity;

        if ($newBalance < 0) {
            throw ValidationException::withMessages([
                'stock' => 'Insufficient stock for product #' . $productId . ' at store ' . $storeKey . '.',
            ]);
        }

        $balance->on_hand = $newBalance;
        $balance->save();

        $this->recordMovement(
            productId: $productId,
            storeKey: $storeKey,
            movementType: $movementType,
            quantity: -$quantity,
            balanceAfter: $newBalance,
            referenceType: $referenceType,
            referenceId: $referenceId,
            notes: $notes,
        );
    }

    protected function applyPurchaseRateCosts(
        int $productId,
        string $storeKey,
        float $receivedQty,
        float $purchaseRate,
        string $referenceType,
        string $referenceId,
    ): void {
        $cost = ProductStoreCost::query()->firstOrCreate(
            [
                'product_id' => $productId,
                'store_key' => $storeKey,
            ],
            [
                'average_cost' => 0,
                'last_purchase_cost' => 0,
            ],
        );

        $onHandBefore = $this->onHand($productId, $storeKey) - $receivedQty;
        $previousAverage = (float) $cost->average_cost;
        $previousLast = (float) $cost->last_purchase_cost;

        $newAverage = $this->weightedAverageCost(
            onHandBefore: max(0, $onHandBefore),
            previousAverage: $previousAverage,
            receivedQty: $receivedQty,
            purchaseRate: $purchaseRate,
        );

        $this->setAverageAndLastCost(
            productId: $productId,
            storeKey: $storeKey,
            averageCost: $newAverage,
            lastPurchaseCost: $purchaseRate,
            referenceType: $referenceType,
            referenceId: $referenceId,
            purchaseRate: $purchaseRate,
            receivedQuantity: $receivedQty,
            previousAverage: $previousAverage,
            previousLast: $previousLast,
        );
    }

    protected function setAverageAndLastCost(
        int $productId,
        string $storeKey,
        float $averageCost,
        float $lastPurchaseCost,
        string $referenceType,
        string $referenceId,
        float $purchaseRate,
        float $receivedQuantity,
        ?float $previousAverage = null,
        ?float $previousLast = null,
    ): void {
        $cost = ProductStoreCost::query()->firstOrCreate(
            [
                'product_id' => $productId,
                'store_key' => $storeKey,
            ],
            [
                'average_cost' => 0,
                'last_purchase_cost' => 0,
            ],
        );

        $previousAverage ??= (float) $cost->average_cost;
        $previousLast ??= (float) $cost->last_purchase_cost;

        $cost->update([
            'average_cost' => round($averageCost, 4),
            'last_purchase_cost' => round($lastPurchaseCost, 4),
        ]);

        PurchaseRateAudit::query()->create([
            'product_id' => $productId,
            'store_key' => $storeKey,
            'previous_average_cost' => $previousAverage,
            'new_average_cost' => round($averageCost, 4),
            'previous_last_purchase_cost' => $previousLast,
            'new_last_purchase_cost' => round($lastPurchaseCost, 4),
            'purchase_rate' => round($purchaseRate, 4),
            'received_quantity' => $receivedQuantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => auth()->id(),
        ]);
    }

    protected function weightedAverageCost(
        float $onHandBefore,
        float $previousAverage,
        float $receivedQty,
        float $purchaseRate,
    ): float {
        if ($receivedQty <= 0) {
            return $previousAverage;
        }

        if ($onHandBefore <= 0 || $previousAverage <= 0) {
            return $purchaseRate;
        }

        $totalValue = ($onHandBefore * $previousAverage) + ($receivedQty * $purchaseRate);
        $totalQty = $onHandBefore + $receivedQty;

        return $totalQty > 0 ? round($totalValue / $totalQty, 4) : $purchaseRate;
    }

    protected function recordMovement(
        int $productId,
        string $storeKey,
        string $movementType,
        float $quantity,
        float $balanceAfter,
        string $referenceType,
        string $referenceId,
        ?string $notes = null,
    ): void {
        InventoryMovement::query()->create([
            'product_id' => $productId,
            'store_key' => $storeKey,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => auth()->id(),
        ]);
    }
}
