<?php

namespace App\Services\ProductCatalog;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CategoryCatalogQuery
{
    /**
     * @return Collection<int, Category>
     */
    public function childrenOf(?int $parentId): Collection
    {
        return $this->baseQuery()
            ->where('parent_id', $parentId)
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();
    }

    /**
     * @return Collection<int, Category>
     */
    public function rootCategories(): Collection
    {
        return $this->childrenOf(null);
    }

    public function findActive(int $id): ?Category
    {
        return $this->baseQuery()->find($id);
    }

    protected function baseQuery(): Builder
    {
        return Category::query()
            ->active()
            ->withCount([
                'children as children_count' => fn (Builder $query) => $query->where('status', Category::STATUS_ACTIVE),
            ]);
    }
}
