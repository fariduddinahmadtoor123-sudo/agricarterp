<?php

namespace App\Services\ProductCatalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductCatalogQuery
{
    public function __construct(
        protected CategoryHierarchyService $hierarchy,
    ) {}

    /**
     * Active products in this category subtree (primary or display tag).
     *
     * @return Collection<int, Product>
     */
    public function productsForCategory(Category $category): Collection
    {
        $categoryIds = $this->hierarchy->activeSubtreeCategoryIds($category);

        return $this->baseQuery()
            ->where(function (Builder $query) use ($categoryIds): void {
                $query
                    ->whereIn('category_id', $categoryIds)
                    ->orWhereHas(
                        'categoryTags',
                        fn (Builder $tagQuery): Builder => $tagQuery->whereIn('categories.id', $categoryIds),
                    );
            })
            ->orderBy('product_number')
            ->get();
    }

    public function productCountForCategorySubtree(Category $category): int
    {
        $categoryIds = $this->hierarchy->activeSubtreeCategoryIds($category);

        if ($categoryIds === []) {
            return 0;
        }

        return $this->baseQuery()
            ->where(function (Builder $query) use ($categoryIds): void {
                $query
                    ->whereIn('category_id', $categoryIds)
                    ->orWhereHas(
                        'categoryTags',
                        fn (Builder $tagQuery): Builder => $tagQuery->whereIn('categories.id', $categoryIds),
                    );
            })
            ->count();
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array<int, int>
     */
    public function productCountsForCategorySubtrees(Collection $categories): array
    {
        $counts = [];

        foreach ($categories as $category) {
            $counts[$category->id] = $this->productCountForCategorySubtree($category);
        }

        return $counts;
    }

    protected function baseQuery(): Builder
    {
        return Product::query()
            ->active()
            ->with([
                'brand',
                'category',
                'baseUnit',
                'packingUnit',
                'images',
            ]);
    }
}
