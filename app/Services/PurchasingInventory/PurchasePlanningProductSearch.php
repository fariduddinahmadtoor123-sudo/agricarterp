<?php

namespace App\Services\PurchasingInventory;

use App\Models\Product;
use App\Services\ProductCatalog\ProductImageStorage;
use Illuminate\Support\Collection;

class PurchasePlanningProductSearch
{
    public function __construct(
        protected ProductImageStorage $imageStorage,
    ) {}

    /**
     * Search active catalog products for the planning worksheet.
     *
     * @return list<array{
     *     id: int,
     *     barcode: string,
     *     sku: string,
     *     name_en: string,
     *     name_ur: string,
     *     display_name: string,
     *     thumbnail_url: ?string
     * }>
     */
    public function search(string $term, int $limit = 12): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $like = '%' . addcslashes($term, '%_\\') . '%';

        $products = Product::query()
            ->active()
            ->with(['images'])
            ->where(function ($query) use ($term, $like): void {
                $query
                    ->where('product_number', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('name_ur', 'like', $like);

                if (mb_strlen($term) >= 2) {
                    $query->orWhere('product_number', $term);
                }
            })
            ->orderBy('name_en')
            ->limit($limit)
            ->get();

        return $products
            ->map(fn (Product $product): array => $this->presentProduct($product))
            ->values()
            ->all();
    }

    public function findExactMatch(string $term): ?array
    {
        $term = trim($term);

        if ($term === '') {
            return null;
        }

        $product = Product::query()
            ->active()
            ->with(['images'])
            ->where(function ($query) use ($term): void {
                $query
                    ->where('product_number', $term)
                    ->orWhereRaw('LOWER(TRIM(name_en)) = ?', [mb_strtolower($term)])
                    ->orWhereRaw('TRIM(name_ur) = ?', [$term]);
            })
            ->first();

        return $product !== null ? $this->presentProduct($product) : null;
    }

    public function findById(int $productId): ?array
    {
        $product = Product::query()
            ->active()
            ->with(['images'])
            ->find($productId);

        return $product !== null ? $this->presentProduct($product) : null;
    }

    /**
     * @return array{
     *     id: int,
     *     barcode: string,
     *     sku: string,
     *     name_en: string,
     *     name_ur: string,
     *     display_name: string,
     *     thumbnail_url: ?string,
     *     required_quantity: float|null,
     *     alert_quantity: float|null,
     *     low_stock: string
     * }
     */
    public function presentProduct(Product $product): array
    {
        $main = $product->images->firstWhere('is_main', true) ?? $product->images->first();
        $number = (string) $product->product_number;

        return [
            'id' => (int) $product->id,
            'barcode' => $number,
            'sku' => $number,
            'name_en' => (string) $product->name_en,
            'name_ur' => (string) $product->name_ur,
            'display_name' => $this->displayName($product),
            'thumbnail_url' => $this->imageStorage->url($main?->image_path),
            'stock' => '',
            'required_quantity' => (float) $product->required_quantity > 0 ? (float) $product->required_quantity : null,
            'alert_quantity' => (float) $product->alert_quantity > 0 ? (float) $product->alert_quantity : null,
            'low_stock' => $product->alert_quantity > 0 ? PurchaseLineBuilder::formatQuantity($product->alert_quantity) : '',
        ];
    }

    protected function displayName(Product $product): string
    {
        $english = trim((string) $product->name_en);
        $urdu = trim((string) $product->name_ur);

        if ($english !== '' && $urdu !== '') {
            return $english . ' / ' . $urdu;
        }

        return $english !== '' ? $english : $urdu;
    }
}
