<?php

namespace App\Services\ProductCatalog;

use App\Models\ProductControl;

class ProductControlQuery
{
    /**
     * @return array<int|string, string>
     */
    public function activeControlOptions(): array
    {
        return ProductControl::query()
            ->active()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function searchActiveControls(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        $term = '%' . addcslashes($search, '%_\\') . '%';

        return ProductControl::query()
            ->active()
            ->where(function ($query) use ($term): void {
                $query
                    ->where('name', 'like', $term)
                    ->orWhere('control_number', 'like', $term);
            })
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->all();
    }

    public function controlLabel(int | string | null $controlId): ?string
    {
        if (blank($controlId)) {
            return null;
        }

        $control = ProductControl::query()->find($controlId);

        if ($control === null) {
            return null;
        }

        return $control->control_number . ' — ' . $control->name;
    }

    /**
     * @param  list<int>  $controlIds
     * @return list<int>
     */
    public function filterAssignableIds(array $controlIds): array
    {
        if ($controlIds === []) {
            return [];
        }

        return ProductControl::query()
            ->active()
            ->whereIn('id', $controlIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
