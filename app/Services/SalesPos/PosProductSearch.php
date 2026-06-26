<?php

namespace App\Services\SalesPos;

use App\Models\Product;
use App\Models\PurchasingInventory\InventoryBalance;
use App\Models\PurchasingInventory\ProductStorePrice;
use App\Services\ProductCatalog\ProductControlAssignmentService;
use App\Services\ProductCatalog\ProductImageStorage;
use App\Services\PurchasingInventory\InventoryService;

class PosProductSearch
{
    public function __construct(
        protected ProductImageStorage $imageStorage,
        protected InventoryService $inventory,
        protected ProductControlAssignmentService $controlAssignment,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, ?string $storeKey = null, ?int $limit = null): array
    {
        $term = trim($term);
        $minChars = (int) config('sales-pos.product_search_min_chars', 2);
        $limit ??= (int) config('sales-pos.product_search_limit', 12);

        if (mb_strlen($term) < $minChars) {
            return [];
        }

        $storeKey ??= $this->inventory->defaultStoreKey();
        $like = '%' . addcslashes($term, '%_\\') . '%';
        $prefix = addcslashes($term, '%_\\') . '%';

        $products = Product::query()
            ->active()
            ->with(['images', 'brand', 'baseUnit'])
            ->where(function ($query) use ($term, $like, $prefix): void {
                $query
                    ->where('product_number', 'like', $prefix)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('name_ur', 'like', $like)
                    ->orWhere('product_number', $term);
            })
            ->orderByRaw(
                'CASE
                    WHEN product_number = ? THEN 0
                    WHEN product_number LIKE ? THEN 1
                    WHEN name_en LIKE ? THEN 2
                    ELSE 3
                END',
                [$term, $prefix, $prefix],
            )
            ->orderBy('name_en')
            ->limit($limit)
            ->get();

        if ($products->isEmpty()) {
            return [];
        }

        $productIds = $products->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $onHandByProduct = $this->onHandByProduct($productIds, $storeKey);
        $saleRateByProduct = $this->saleRateByProduct($productIds, $storeKey);

        return $products
            ->map(fn (Product $product): array => $this->presentSearchResult(
                $product,
                $onHandByProduct[(int) $product->id] ?? 0.0,
                $saleRateByProduct[(int) $product->id] ?? 0.0,
            ))
            ->values()
            ->all();
    }

    public function findExactMatch(string $term, ?string $storeKey = null): ?array
    {
        $term = trim($term);

        if ($term === '') {
            return null;
        }

        $storeKey ??= $this->inventory->defaultStoreKey();

        $product = Product::query()
            ->active()
            ->with(['images', 'brand', 'baseUnit', 'attributeValues.attribute', 'controlGroups', 'individualControls'])
            ->where(function ($query) use ($term): void {
                $query
                    ->where('product_number', $term)
                    ->orWhereRaw('LOWER(TRIM(name_en)) = ?', [mb_strtolower($term)])
                    ->orWhereRaw('TRIM(name_ur) = ?', [$term]);
            })
            ->first();

        return $product !== null ? $this->presentProduct($product, $storeKey) : null;
    }

    public function findById(int $productId, ?string $storeKey = null): ?array
    {
        $storeKey ??= $this->inventory->defaultStoreKey();

        $product = Product::query()
            ->active()
            ->with(['images', 'brand', 'baseUnit', 'attributeValues.attribute', 'controlGroups', 'individualControls'])
            ->find($productId);

        return $product !== null ? $this->presentProduct($product, $storeKey) : null;
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, float>
     */
    protected function onHandByProduct(array $productIds, string $storeKey): array
    {
        if ($productIds === []) {
            return [];
        }

        return InventoryBalance::query()
            ->where('store_key', $storeKey)
            ->whereIn('product_id', $productIds)
            ->pluck('on_hand', 'product_id')
            ->map(fn ($value): float => max(0, (float) $value))
            ->all();
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, float>
     */
    protected function saleRateByProduct(array $productIds, string $storeKey): array
    {
        if ($productIds === []) {
            return [];
        }

        return ProductStorePrice::query()
            ->where('store_key', $storeKey)
            ->whereIn('product_id', $productIds)
            ->pluck('sale_rate', 'product_id')
            ->map(fn ($value): float => max(0, (float) $value))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentSearchResult(Product $product, float $onHand, float $saleRate): array
    {
        $main = $product->images->firstWhere('is_main', true) ?? $product->images->first();

        return [
            'id' => (int) $product->id,
            'barcode' => (string) $product->product_number,
            'product_number' => (string) $product->product_number,
            'name_en' => (string) $product->name_en,
            'name_ur' => (string) $product->name_ur,
            'brand_name' => (string) ($product->brand?->name_en ?? ''),
            'unit_label' => (string) ($product->baseUnit?->name_en ?? $product->baseUnit?->symbol ?? ''),
            'thumbnail_url' => $this->imageStorage->url($main?->image_path),
            'sale_rate' => $saleRate > 0 ? PosSaleLineBuilder::formatAmount($saleRate) : '',
            'on_hand' => PosSaleLineBuilder::formatQuantity($onHand),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentProduct(Product $product, string $storeKey): array
    {
        $main = $product->images->firstWhere('is_main', true) ?? $product->images->first();
        $price = ProductStorePrice::query()
            ->where('product_id', $product->id)
            ->where('store_key', $storeKey)
            ->first();

        $saleRate = (float) ($price?->sale_rate ?? 0);

        $controls = $this->controlAssignment->effectiveControlLabels(
            $product->controlGroups->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $product->individualControls->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );

        $attributesLabel = $product->attributeValues
            ->map(function ($row): string {
                $name = trim((string) ($row->attribute?->name ?? ''));

                if ($name === '' || blank($row->value)) {
                    return '';
                }

                return $name . ': ' . trim((string) $row->value);
            })
            ->filter()
            ->implode(' · ');

        return [
            'id' => (int) $product->id,
            'barcode' => (string) $product->product_number,
            'product_number' => (string) $product->product_number,
            'name_en' => (string) $product->name_en,
            'name_ur' => (string) $product->name_ur,
            'brand_name' => (string) ($product->brand?->name_en ?? ''),
            'attributes_label' => $attributesLabel,
            'controls' => $controls,
            'unit_label' => (string) ($product->baseUnit?->name_en ?? $product->baseUnit?->symbol ?? ''),
            'thumbnail_url' => $this->imageStorage->url($main?->image_path),
            'sale_rate' => $saleRate > 0 ? PosSaleLineBuilder::formatAmount($saleRate) : '',
            'on_hand' => PosSaleLineBuilder::formatQuantity($this->inventory->onHand((int) $product->id, $storeKey)),
        ];
    }
}
