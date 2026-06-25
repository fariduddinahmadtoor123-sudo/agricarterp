<?php

namespace App\Services\ProductCatalog;

use App\Models\ProductControlGroup;
use App\Support\ProductCatalog\ProductControlAuthorization;
use Illuminate\Support\Facades\DB;

class ProductControlGroupPersistenceService
{
    public function __construct(
        protected ProductControlGroupCodeGenerator $codeGenerator,
        protected ProductControlGroupDataValidator $dataValidator,
        protected ProductControlQuery $controlQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProductControlGroup
    {
        $data = $this->prepareData($data);

        $this->dataValidator->validate($data);

        return DB::transaction(function () use ($data): ProductControlGroup {
            $group = ProductControlGroup::query()->create([
                'name' => $data['name'],
                'group_number' => $this->codeGenerator->generate(),
                'status' => ProductControlGroup::STATUS_ACTIVE,
                'controls_count' => 0,
            ]);

            $this->syncControls($group, $data['control_ids'] ?? []);

            return $group->fresh(['controls']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProductControlGroup $group, array $data): ProductControlGroup
    {
        if ($group->isArchived()) {
            abort(404);
        }

        $data = $this->prepareData($data);

        $this->dataValidator->validate($data, $group);

        return DB::transaction(function () use ($group, $data): ProductControlGroup {
            $group->update([
                'name' => $data['name'],
            ]);

            if (array_key_exists('control_ids', $data)) {
                $this->syncControls($group, $data['control_ids'] ?? []);
            }

            return $group->fresh(['controls']);
        });
    }

    public function archive(ProductControlGroup $group): void
    {
        if (! ProductControlAuthorization::canArchive()) {
            abort(403);
        }

        if ($group->isArchived()) {
            return;
        }

        $group->update([
            'status' => ProductControlGroup::STATUS_ARCHIVED,
        ]);
    }

    public function restore(ProductControlGroup $group): ProductControlGroup
    {
        if (! ProductControlAuthorization::canRestore()) {
            abort(403);
        }

        if ($group->isArchived()) {
            $group->update([
                'status' => ProductControlGroup::STATUS_ACTIVE,
            ]);
        }

        return $group->fresh(['controls']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data): array
    {
        if (array_key_exists('name', $data) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        if (array_key_exists('control_ids', $data) && is_array($data['control_ids'])) {
            $data['control_ids'] = array_values(array_unique(array_map(
                fn ($id): int => (int) $id,
                array_filter($data['control_ids']),
            )));
        }

        return $data;
    }

    /**
     * @param  list<int>  $controlIds
     */
    protected function syncControls(ProductControlGroup $group, array $controlIds): void
    {
        $assignableIds = $this->controlQuery->filterAssignableIds($controlIds);

        $sync = [];

        foreach ($assignableIds as $index => $controlId) {
            $sync[$controlId] = ['sort_order' => ($index + 1) * 10];
        }

        $group->controls()->sync($sync);

        $group->update([
            'controls_count' => count($assignableIds),
        ]);
    }
}
