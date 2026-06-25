<?php

namespace App\Services\PurchasingInventory;

use App\Models\Product;
use App\Support\PurchasingInventory\ReOrderQueueRepository;

class ReOrderCandidateService
{
    public function __construct(
        protected PurchasePlanningProductSearch $productSearch,
        protected InventoryService $inventory,
        protected ReOrderQueueRepository $queueRepository,
        protected ReOrderLineBuilder $lineBuilder,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function candidates(
        string $stockFilter = 'all',
        ?int $categoryId = null,
        int $limit = 500,
    ): array {
        $this->queueRepository->flagStaleOrders();

        $blockedIds = $this->queueRepository->activeProductIds();

        $query = Product::query()
            ->active()
            ->with(['images', 'category'])
            ->when($categoryId !== null, function ($query) use ($categoryId): void {
                $query->where('category_id', $categoryId);
            })
            ->whereNotIn('id', $blockedIds)
            ->orderBy('name_en')
            ->limit($limit);

        $products = $query->get();

        return $products
            ->map(fn (Product $product): ?array => $this->presentCandidate($product))
            ->filter()
            ->filter(function (?array $row) use ($stockFilter): bool {
                if ($row === null) {
                    return false;
                }

                return match ($stockFilter) {
                    'low' => ($row['stock_status'] ?? '') === 'low',
                    'out' => ($row['stock_status'] ?? '') === 'out',
                    default => in_array($row['stock_status'] ?? '', ['low', 'out'], true),
                };
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function presentCandidate(Product $product): ?array
    {
        $status = $this->inventory->stockStatus((int) $product->id);
        $onHand = $this->inventory->onHand((int) $product->id);

        if (! in_array($status, ['low', 'out'], true)) {
            return null;
        }

        $presented = $this->productSearch->presentProduct($product);
        $line = $this->lineBuilder->fromProduct($presented, $onHand);

        return array_merge($line, [
            'stock_status' => $status,
            'on_hand' => PurchaseLineBuilder::formatQuantity($onHand),
            'alert_qty' => PurchaseLineBuilder::formatQuantity($product->alert_quantity),
            'category_name' => (string) ($product->category?->name_en ?? ''),
        ]);
    }

    /**
     * @param  list<int>  $productIds
     * @return list<array<string, mixed>>
     */
    public function linesForProductIds(array $productIds): array
    {
        return collect($productIds)
            ->map(function (int $productId): ?array {
                $product = $this->productSearch->findById($productId);

                if ($product === null) {
                    return null;
                }

                return $this->lineBuilder->fromProduct(
                    $product,
                    $this->inventory->onHand($productId),
                );
            })
            ->filter()
            ->values()
            ->all();
    }
}
