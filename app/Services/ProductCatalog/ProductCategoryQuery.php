<?php

namespace App\Services\ProductCatalog;

use App\Models\Category;

class ProductCategoryQuery
{
    /**
     * @return array<int|string, string>
     */
    public function activeLeafCategoryOptions(): array
    {
        return Category::query()
            ->active()
            ->where('is_leaf', true)
            ->orderBy('name_en')
            ->pluck('name_en', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function searchActiveLeafCategories(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        return $this->searchCategoryResults(
            Category::query()
                ->active()
                ->where('is_leaf', true),
            $search,
        );
    }

    /**
     * @return array<int|string, string>
     */
    public function activeDisplayCategoryOptions(?int $excludeCategoryId = null): array
    {
        $query = Category::query()
            ->active()
            ->orderBy('name_en');

        if ($excludeCategoryId !== null) {
            $query->where('id', '!=', $excludeCategoryId);
        }

        return $query->pluck('name_en', 'id')->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function searchActiveDisplayCategories(string $search, ?int $excludeCategoryId = null): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        $query = Category::query()->active();

        if ($excludeCategoryId !== null) {
            $query->where('id', '!=', $excludeCategoryId);
        }

        return $this->searchCategoryResults($query, $search);
    }

    public function categoryPathLabel(int | string | null $categoryId): ?string
    {
        if (blank($categoryId)) {
            return null;
        }

        return Category::query()->find($categoryId)?->full_path;
    }

    public function categoryNameLabel(int | string | null $categoryId): ?string
    {
        if (blank($categoryId)) {
            return null;
        }

        return Category::query()->find($categoryId)?->name_en;
    }

    public function isActiveLeaf(int $categoryId): bool
    {
        $category = Category::query()->find($categoryId);

        return $category !== null && $category->isActive() && $category->is_leaf;
    }

    /**
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    public function filterAssignableDisplayTagIds(array $categoryIds, int $primaryCategoryId): array
    {
        if ($categoryIds === []) {
            return [];
        }

        return Category::query()
            ->active()
            ->where('id', '!=', $primaryCategoryId)
            ->whereIn('id', $categoryIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Category>  $query
     * @return array<int|string, string>
     */
    protected function searchCategoryResults($query, string $search): array
    {
        $term = '%' . addcslashes($search, '%_\\') . '%';

        $tokens = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $query
            ->where(function ($query) use ($term, $tokens): void {
                $query
                    ->where('name_en', 'like', $term)
                    ->orWhere('full_path', 'like', $term)
                    ->orWhere('category_number', 'like', $term);

                foreach ($tokens as $token) {
                    $tokenTerm = '%' . addcslashes($token, '%_\\') . '%';

                    $query->orWhere('name_en', 'like', $tokenTerm)
                        ->orWhere('full_path', 'like', $tokenTerm);
                }
            })
            ->orderBy('name_en')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Category $category): array => [
                $category->id => $category->name_en,
            ])
            ->all();
    }
}
