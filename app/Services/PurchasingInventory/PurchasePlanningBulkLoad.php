<?php

namespace App\Services\PurchasingInventory;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductCatalog\CategoryHierarchyService;
use Illuminate\Support\Collection;

class PurchasePlanningBulkLoad
{
    public function __construct(
        protected PurchasePlanningProductSearch $productSearch,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function allProducts(int $limit = 500): array
    {
        return $this->mapProducts(
            Product::query()
                ->active()
                ->with(['images'])
                ->orderBy('name_en')
                ->limit($limit)
                ->get(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function byCategory(int $categoryId, int $limit = 500): array
    {
        $category = Category::query()->active()->find($categoryId);

        if ($category === null) {
            return [];
        }

        $categoryIds = $this->resolveSubtreeCategoryIds($category);

        if ($categoryIds === []) {
            return [];
        }

        return $this->mapProducts(
            Product::query()
                ->active()
                ->with(['images'])
                ->whereIn('category_id', $categoryIds)
                ->orderBy('name_en')
                ->limit($limit)
                ->get(),
        );
    }

    /**
     * @return list<int>
     */
    protected function resolveSubtreeCategoryIds(Category $category): array
    {
        $fullPath = trim((string) $category->full_path);

        if ($fullPath !== '') {
            try {
                return app(CategoryHierarchyService::class)->activeSubtreeCategoryIds($category);
            } catch (\Throwable) {
                return $this->collectDescendantIdsIncludingSelf($category->id);
            }
        }

        return $this->collectDescendantIdsIncludingSelf($category->id);
    }

    /**
     * @return list<int>
     */
    protected function collectDescendantIdsIncludingSelf(int $categoryId): array
    {
        $ids = [$categoryId];

        $childIds = Category::query()
            ->active()
            ->where('parent_id', $categoryId)
            ->pluck('id');

        foreach ($childIds as $childId) {
            $ids = array_merge($ids, $this->collectDescendantIdsIncludingSelf((int) $childId));
        }

        return array_values(array_unique($ids));
    }

    /**
     * Products with a low-stock alert threshold configured.
     *
     * @return list<array<string, mixed>>
     */
    public function lowStock(int $limit = 500): array
    {
        return $this->mapProducts(
            Product::query()
                ->active()
                ->with(['images'])
                ->where('alert_quantity', '>', 0)
                ->orderBy('name_en')
                ->limit($limit)
                ->get(),
        );
    }

    /**
     * Preview placeholder until inventory is integrated.
     *
     * @return list<array<string, mixed>>
     */
    public function outOfStock(int $limit = 500): array
    {
        return $this->mapProducts(
            Product::query()
                ->active()
                ->with(['images'])
                ->where('required_quantity', '<=', 0)
                ->orderBy('name_en')
                ->limit($limit)
                ->get(),
        );
    }

    /**
     * @deprecated Use PurchasePlanningCategorySearch instead.
     * @return Collection<int, Category>
     */
    public function categoryOptions(): Collection
    {
        return Category::query()
            ->where('status', Category::STATUS_ACTIVE)
            ->orderBy('full_path')
            ->get(['id', 'name_en', 'full_path']);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    protected function mapProducts(Collection $products): array
    {
        return $products
            ->map(fn (Product $product): array => $this->productSearch->presentProduct($product))
            ->values()
            ->all();
    }
}
