<?php

namespace App\Services\ProductCatalog;

use App\Models\Brand;
use App\Models\Unit;

class ProductCatalogMasterQuery
{
    /**
     * @return array<int|string, string>
     */
    public function activeBrandOptions(): array
    {
        return Brand::query()
            ->active()
            ->orderBy('name_en')
            ->pluck('name_en', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function searchActiveBrands(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        $term = '%' . addcslashes($search, '%_\\') . '%';

        return Brand::query()
            ->active()
            ->where(function ($query) use ($term): void {
                $query
                    ->where('name_en', 'like', $term)
                    ->orWhere('brand_number', 'like', $term);
            })
            ->orderBy('name_en')
            ->limit(50)
            ->pluck('name_en', 'id')
            ->all();
    }

    public function brandLabel(int | string | null $brandId): ?string
    {
        if (blank($brandId)) {
            return null;
        }

        return Brand::query()->find($brandId)?->name_en;
    }

    /**
     * @return array<int|string, string>
     */
    public function activeUnitOptions(): array
    {
        return Unit::query()
            ->active()
            ->orderBy('name_en')
            ->get()
            ->mapWithKeys(fn (Unit $unit): array => [
                $unit->id => $unit->name_en . ' (' . $unit->abbreviation_en . ')',
            ])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function searchActiveUnits(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        $term = '%' . addcslashes($search, '%_\\') . '%';

        return Unit::query()
            ->active()
            ->where(function ($query) use ($term): void {
                $query
                    ->where('name_en', 'like', $term)
                    ->orWhere('abbreviation_en', 'like', $term)
                    ->orWhere('unit_number', 'like', $term);
            })
            ->orderBy('name_en')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Unit $unit): array => [
                $unit->id => $unit->name_en . ' (' . $unit->abbreviation_en . ')',
            ])
            ->all();
    }

    public function unitLabel(int | string | null $unitId): ?string
    {
        if (blank($unitId)) {
            return null;
        }

        $unit = Unit::query()->find($unitId);

        if ($unit === null) {
            return null;
        }

        return $unit->name_en . ' (' . $unit->abbreviation_en . ')';
    }

    /**
     * @param  list<int>  $unitIds
     * @return list<int>
     */
    public function filterAssignableUnitIds(array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        return Unit::query()
            ->active()
            ->whereIn('id', $unitIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $brandIds
     */
    public function filterAssignableBrandId(int $brandId): ?int
    {
        return Brand::query()
            ->active()
            ->where('id', $brandId)
            ->value('id');
    }
}
