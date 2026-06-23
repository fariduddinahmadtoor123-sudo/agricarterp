<?php

namespace App\Services\PurchasingInventory;

use App\Models\Category;

class PurchasePlanningCategorySearch
{
    /**
     * Search active categories by English/Urdu name, full path, or category number.
     *
     * @return list<array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     path_hint: ?string,
     *     level: int,
     *     is_leaf: bool
     * }>
     */
    public function search(string $term, int $limit = 25): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $like = '%' . addcslashes($term, '%_\\') . '%';
        $tokens = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return Category::query()
            ->active()
            ->where(function ($query) use ($like, $tokens, $term): void {
                $query
                    ->where('name_en', 'like', $like)
                    ->orWhere('name_ur', 'like', $like)
                    ->orWhere('full_path', 'like', $like)
                    ->orWhere('category_number', 'like', $like)
                    ->orWhere('visual_mapping_code', 'like', $like);

                foreach ($tokens as $token) {
                    $tokenLike = '%' . addcslashes($token, '%_\\') . '%';

                    $query
                        ->orWhere('name_en', 'like', $tokenLike)
                        ->orWhere('name_ur', 'like', $tokenLike)
                        ->orWhere('full_path', 'like', $tokenLike)
                        ->orWhere('category_number', 'like', $tokenLike);
                }

                if (mb_strlen($term) >= 2) {
                    $query->orWhere('category_number', $term);
                }
            })
            ->orderBy('full_path')
            ->orderBy('name_en')
            ->limit($limit)
            ->get(['id', 'name_en', 'name_ur', 'full_path', 'level', 'is_leaf'])
            ->map(fn (Category $category): array => $this->presentCategory($category))
            ->values()
            ->all();
    }

    public function labelForId(int $categoryId): ?string
    {
        $category = Category::query()
            ->active()
            ->find($categoryId);

        return $category !== null ? $this->shortName($category) : null;
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     label: string,
     *     path_hint: ?string,
     *     level: int,
     *     is_leaf: bool
     * }
     */
    protected function presentCategory(Category $category): array
    {
        $name = $this->shortName($category);

        return [
            'id' => (int) $category->id,
            'name' => $name,
            'label' => $name,
            'path_hint' => $this->pathHint($category, $name),
            'level' => (int) $category->level,
            'is_leaf' => (bool) $category->is_leaf,
        ];
    }

    protected function shortName(Category $category): string
    {
        $english = trim((string) $category->name_en);
        $urdu = trim((string) $category->name_ur);

        if ($english !== '') {
            return $english;
        }

        return $urdu;
    }

    protected function pathHint(Category $category, string $name): ?string
    {
        $path = trim((string) $category->full_path);

        if ($path === '') {
            return null;
        }

        $hint = $path;

        if ($name !== '' && str_ends_with($path, $name)) {
            $hint = trim(mb_substr($path, 0, mb_strlen($path) - mb_strlen($name)), " \u{203A}");
        }

        if ($hint === '' || $hint === $name) {
            return null;
        }

        if (mb_strlen($hint) > 72) {
            $hint = '… ' . mb_substr($hint, -69);
        }

        return $hint;
    }
}
