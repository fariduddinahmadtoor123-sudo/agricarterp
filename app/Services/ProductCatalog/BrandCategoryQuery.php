<?php

namespace App\Services\ProductCatalog;

use App\Models\Category;

class BrandCategoryQuery
{
    /**
     * @return array<int|string, string>
     */
    public function activeCategoryOptions(): array
    {
        return Category::query()
            ->active()
            ->orderBy('name_en')
            ->pluck('name_en', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function searchActiveCategories(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        $term = '%' . addcslashes($search, '%_\\') . '%';

        return Category::query()
            ->active()
            ->where(function ($query) use ($term): void {
                $query
                    ->where('name_en', 'like', $term)
                    ->orWhere('category_number', 'like', $term);
            })
            ->orderBy('name_en')
            ->limit(50)
            ->pluck('name_en', 'id')
            ->all();
    }

    public function categoryLabel(int | string | null $categoryId): ?string
    {
        if (blank($categoryId)) {
            return null;
        }

        return Category::query()->find($categoryId)?->name_en;
    }

    /**
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    public function filterAssignableIds(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        return Category::query()
            ->active()
            ->whereIn('id', $categoryIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
