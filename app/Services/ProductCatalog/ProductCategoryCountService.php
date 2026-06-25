<?php

namespace App\Services\ProductCatalog;

use App\Models\Category;
use App\Models\Product;

class ProductCategoryCountService
{
    /**
     * @return list<int>
     */
    public function listingCategoryIds(Product $product): array
    {
        $tagIds = $product->relationLoaded('categoryTags')
            ? $product->categoryTags->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : $product->categoryTags()->pluck('categories.id')->map(fn ($id): int => (int) $id)->all();

        return array_values(array_unique(array_merge(
            [(int) $product->category_id],
            $tagIds,
        )));
    }

    /**
     * @param  list<int>  $categoryIds
     */
    public function applyDelta(array $categoryIds, int $delta): void
    {
        if ($delta === 0 || $categoryIds === []) {
            return;
        }

        foreach (array_unique($categoryIds) as $categoryId) {
            $category = Category::query()->lockForUpdate()->find($categoryId);

            if ($category === null) {
                continue;
            }

            $category->update([
                'products_count' => max(0, (int) $category->products_count + $delta),
            ]);
        }
    }

    public function registerActiveProduct(Product $product): void
    {
        if (! $product->isActive()) {
            return;
        }

        $this->applyDelta($this->listingCategoryIds($product), 1);
    }

    public function unregisterActiveProduct(Product $product): void
    {
        if (! $product->isActive()) {
            return;
        }

        $this->applyDelta($this->listingCategoryIds($product), -1);
    }

    /**
     * @param  list<int>  $previousListingCategoryIds
     */
    public function reconcileListingChange(Product $product, array $previousListingCategoryIds, bool $wasActive): void
    {
        $previous = $wasActive ? array_values(array_unique($previousListingCategoryIds)) : [];
        $current = $product->isActive() ? $this->listingCategoryIds($product) : [];

        $removed = array_diff($previous, $current);
        $added = array_diff($current, $previous);

        $this->applyDelta(array_values($removed), -1);
        $this->applyDelta(array_values($added), 1);
    }
}
