<?php

namespace App\Support\Catalog;

use App\Models\Product;
use App\Services\ProductCatalog\ProductImageStorage;
use Illuminate\Support\Collection;

class ProductCatalogPresenter
{
    public function __construct(
        protected ProductImageStorage $imageStorage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function card(Product $product): array
    {
        $mainImage = $product->images->firstWhere('is_main', true);

        return [
            'id' => $product->id,
            'product_number' => $product->product_number,
            'name_en' => $product->name_en,
            'name_ur' => filled($product->name_ur) ? $product->name_ur : null,
            'image_url' => $this->imageStorage->catalogUrl($mainImage?->image_path),
            'brand' => $product->brand?->name_en,
            'category' => $product->category?->name_en,
            'packing' => $this->packingLabel($product),
        ];
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    public function cards(Collection $products): array
    {
        return $products
            ->map(fn (Product $product): array => $this->card($product))
            ->all();
    }

    protected function packingLabel(Product $product): string
    {
        $base = $product->baseUnit?->abbreviation_en ?? '';
        $pack = $product->packingUnit?->abbreviation_en ?? '';

        return trim($product->packing_value . ' ' . $base . ' / ' . $pack);
    }
}
