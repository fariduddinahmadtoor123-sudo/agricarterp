<?php

namespace App\Services\ProductCatalog;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryHierarchyService
{
    public function __construct(
        protected CategoryVisualMappingService $visualMapping,
        protected CategoryPathService $pathService,
    ) {}

    public function nextSortOrder(?int $parentId): int
    {
        $max = Category::query()
            ->where('parent_id', $parentId)
            ->max('sort_order');

        return ((int) $max) + 1;
    }

    public function isDescendantOf(Category $category, int $ancestorId): bool
    {
        $current = $category->parent;

        while ($current !== null) {
            if ($current->id === $ancestorId) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }

    public function assertActiveParent(?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $parent = Category::query()->find($parentId);

        if ($parent === null || $parent->isArchived()) {
            throw new \InvalidArgumentException('Parent category must be active.');
        }
    }

    public function refreshLeafStatus(?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        $category = Category::query()->find($categoryId);

        if ($category === null) {
            return;
        }

        $hasChildren = Category::query()
            ->where('parent_id', $category->id)
            ->exists();

        $category->update([
            'is_leaf' => ! $hasChildren,
        ]);
    }

    /**
     * Rebuild level, visual mapping code, and full path for a node and all descendants.
     */
    public function rebuildSubtree(Category $category): void
    {
        $category->loadMissing('parent');

        $this->applyHierarchyMetadata($category);

        $children = Category::query()
            ->where('parent_id', $category->id)
            ->orderBy('sort_order')
            ->get();

        foreach ($children as $child) {
            $this->rebuildSubtree($child);
        }
    }

    public function applyHierarchyMetadata(Category $category): void
    {
        $parent = $category->parent_id
            ? Category::query()->find($category->parent_id)
            : null;

        $level = $parent ? $parent->level + 1 : 0;

        $category->level = $level;
        $category->visual_mapping_code = $this->visualMapping->buildCode(
            $parent?->visual_mapping_code,
            $level,
            (int) $category->sort_order,
        );
        $category->full_path = $this->pathService->buildFullPath(
            $parent?->full_path,
            $category->name_en,
        );
        $category->save();
    }

    /**
     * @return Collection<int, Category>
     */
    public function ancestors(Category $category): Collection
    {
        $ancestors = collect();
        $current = $category->parent;

        while ($current !== null) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * @return array<string, string>
     */
    public function parentOptions(?Category $exclude = null): array
    {
        return $this->parentOptionsShort($exclude);
    }

    public function parentShortLabel(int | string | null $categoryId): ?string
    {
        if (blank($categoryId)) {
            return null;
        }

        $category = Category::query()->find($categoryId);

        if ($category === null) {
            return null;
        }

        return $category->name_en;
    }

    /**
     * Compact labels for the parent select trigger (selected value).
     *
     * @return array<int|string, string>
     */
    public function parentOptionsShort(?Category $exclude = null): array
    {
        return $this->parentOptionQuery($exclude)
            ->get()
            ->mapWithKeys(fn (Category $category): array => [
                $category->id => $category->name_en,
            ])
            ->all();
    }

    /**
     * Name-only labels for parent search dropdown results.
     *
     * @return array<int|string, string>
     */
    public function searchParentOptions(string $search, ?Category $exclude = null): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        $term = '%' . addcslashes($search, '%_\\') . '%';

        return $this->parentOptionQuery($exclude)
            ->where(function ($query) use ($term): void {
                $query
                    ->where('name_en', 'like', $term)
                    ->orWhere('category_number', 'like', $term);
            })
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Category $category): array => [
                $category->id => $category->name_en,
            ])
            ->all();
    }

    protected function parentOptionQuery(?Category $exclude = null)
    {
        $query = Category::query()
            ->active()
            ->orderBy('visual_mapping_code');

        if ($exclude !== null) {
            $query->where('id', '!=', $exclude->id);

            $descendantIds = $this->collectDescendantIds($exclude);
            if ($descendantIds !== []) {
                $query->whereNotIn('id', $descendantIds);
            }
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    public function activeSubtreeCategoryIds(Category $category): array
    {
        $pathPrefix = addcslashes($category->full_path, '%_\\') . ' › %';

        return Category::query()
            ->active()
            ->where(function ($query) use ($category, $pathPrefix): void {
                $query
                    ->where('id', $category->id)
                    ->orWhere('full_path', 'like', $pathPrefix);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    protected function collectDescendantIds(Category $category): array
    {
        $ids = [];
        $children = Category::query()->where('parent_id', $category->id)->get();

        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->collectDescendantIds($child));
        }

        return $ids;
    }
}
