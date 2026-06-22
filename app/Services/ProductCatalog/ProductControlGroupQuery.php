<?php

namespace App\Services\ProductCatalog;

use App\Models\ProductControl;
use App\Models\ProductControlGroup;

class ProductControlGroupQuery
{
    /**
     * @return array<int|string, string>
     */
    public function activeGroupOptions(): array
    {
        return ProductControlGroup::query()
            ->active()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function searchActiveGroups(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        $term = '%' . addcslashes($search, '%_\\') . '%';

        return ProductControlGroup::query()
            ->active()
            ->where(function ($query) use ($term): void {
                $query
                    ->where('name', 'like', $term)
                    ->orWhere('group_number', 'like', $term);
            })
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->all();
    }

    public function groupLabel(int | string | null $groupId): ?string
    {
        if (blank($groupId)) {
            return null;
        }

        $group = ProductControlGroup::query()->find($groupId);

        if ($group === null) {
            return null;
        }

        return $group->group_number . ' — ' . $group->name;
    }

    /**
     * @param  list<int>  $groupIds
     * @return list<int>
     */
    public function filterAssignableGroupIds(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        return ProductControlGroup::query()
            ->active()
            ->whereIn('id', $groupIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $groupIds
     * @return list<int>
     */
    public function controlIdsFromGroups(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        return ProductControl::query()
            ->whereHas('groups', fn ($query) => $query->whereIn('product_control_groups.id', $groupIds))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
