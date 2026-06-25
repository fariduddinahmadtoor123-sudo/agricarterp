<?php

namespace App\Services\ProductCatalog;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
     * @param  Builder<Category>  $query
     * @return array<int|string, string>
     */
    protected function searchCategoryResults(Builder $query, string $search): array
    {
        $normalized = $this->normalizeCategorySearchTerm($search);

        if ($normalized === '') {
            return [];
        }

        $categories = (clone $query)
            ->select(['id', 'name_en', 'full_path', 'category_number', 'visual_mapping_code'])
            ->where(function (Builder $builder) use ($normalized, $search): void {
                $this->applyCategorySearchFilters($builder, $normalized, $search);
            })
            ->limit(200)
            ->get();

        return $this->rankCategorySearchResults($categories, $normalized);
    }

    /**
     * @param  Builder<Category>  $query
     */
    protected function applyCategorySearchFilters(Builder $query, string $normalized, string $rawSearch): void
    {
        if ($this->looksLikePath($rawSearch)) {
            $pathTerm = '%' . addcslashes($normalized, '%_\\') . '%';

            $query->where('full_path', 'like', $pathTerm);

            return;
        }

        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($tokens) > 1) {
            foreach ($tokens as $token) {
                $tokenTerm = '%' . addcslashes($token, '%_\\') . '%';

                $query->where(function (Builder $builder) use ($tokenTerm): void {
                    $builder
                        ->where('name_en', 'like', $tokenTerm)
                        ->orWhere('full_path', 'like', $tokenTerm)
                        ->orWhere('category_number', 'like', $tokenTerm);
                });
            }

            return;
        }

        $term = '%' . addcslashes($normalized, '%_\\') . '%';

        $query->where(function (Builder $builder) use ($term): void {
            $builder
                ->where('name_en', 'like', $term)
                ->orWhere('full_path', 'like', $term)
                ->orWhere('category_number', 'like', $term);
        });
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array<int|string, string>
     */
    protected function rankCategorySearchResults(Collection $categories, string $normalized): array
    {
        $searchLower = mb_strtolower($normalized);

        $scored = $categories
            ->map(fn (Category $category): array => [
                'category' => $category,
                'score' => $this->scoreCategoryMatch($category, $searchLower),
            ])
            ->filter(fn (array $item): bool => $item['score'] > 0)
            ->sort(function (array $left, array $right): int {
                if ($left['score'] !== $right['score']) {
                    return $right['score'] <=> $left['score'];
                }

                $pathCompare = strcmp(
                    (string) $left['category']->full_path,
                    (string) $right['category']->full_path,
                );

                if ($pathCompare !== 0) {
                    return $pathCompare;
                }

                return strcmp((string) $left['category']->name_en, (string) $right['category']->name_en);
            })
            ->values();

        if ($scored->isEmpty()) {
            return [];
        }

        $topScore = $scored->first()['score'];

        if ($topScore >= 10_000) {
            $scored = $scored->filter(fn (array $item): bool => $item['score'] >= 10_000);
        } elseif ($topScore >= 9_000) {
            $scored = $scored->filter(fn (array $item): bool => $item['score'] >= 9_000);
        }

        return $scored
            ->take(50)
            ->mapWithKeys(fn (array $item): array => [
                $item['category']->id => $this->searchResultLabel($item['category']),
            ])
            ->all();
    }

    protected function scoreCategoryMatch(Category $category, string $searchLower): int
    {
        $name = mb_strtolower(trim((string) $category->name_en));
        $path = mb_strtolower(trim((string) $category->full_path));
        $number = mb_strtolower(trim((string) $category->category_number));
        $lastSegment = $this->lastPathSegmentName($category);

        if ($path !== '' && $path === $searchLower) {
            return 10_000;
        }

        if ($number !== '' && $number === $searchLower) {
            return 9_800;
        }

        if ($name !== '' && $name === $searchLower) {
            return 9_000;
        }

        if ($lastSegment !== '' && $lastSegment === $searchLower) {
            return 8_800;
        }

        if ($name !== '' && str_starts_with($name, $searchLower)) {
            return 7_500;
        }

        if ($lastSegment !== '' && str_starts_with($lastSegment, $searchLower)) {
            return 7_400;
        }

        if ($path !== '' && str_starts_with($path, $searchLower)) {
            return 7_000;
        }

        if ($name !== '' && str_contains($name, $searchLower)) {
            return 5_000;
        }

        if ($path !== '' && str_contains($path, $searchLower)) {
            return 4_500;
        }

        if ($number !== '' && str_contains($number, $searchLower)) {
            return 4_000;
        }

        return 100;
    }

    protected function lastPathSegmentName(Category $category): string
    {
        $path = trim((string) $category->full_path);

        if ($path === '') {
            return '';
        }

        $segments = array_map(trim(...), explode('›', $path));
        $lastSegment = (string) end($segments);

        return mb_strtolower($lastSegment);
    }

    protected function searchResultLabel(Category $category): string
    {
        $path = trim((string) $category->full_path);

        if ($path !== '') {
            return $path;
        }

        return trim((string) $category->name_en);
    }

    protected function normalizeCategorySearchTerm(string $search): string
    {
        $search = trim(preg_replace('/\s+/u', ' ', $search) ?? $search);
        $search = preg_replace('/\s*(?:->|›|>|\/)\s*/u', ' › ', $search) ?? $search;

        return trim($search);
    }

    protected function looksLikePath(string $search): bool
    {
        return str_contains($search, '›')
            || preg_match('/(?:->|>|\/)/', $search) === 1;
    }
}
