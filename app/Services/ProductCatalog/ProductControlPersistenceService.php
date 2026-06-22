<?php

namespace App\Services\ProductCatalog;

use App\Models\ProductControl;
use App\Support\ProductCatalog\ProductControlAuthorization;
use Illuminate\Support\Facades\DB;

class ProductControlPersistenceService
{
    public function __construct(
        protected ProductControlCodeGenerator $codeGenerator,
        protected ProductControlDataValidator $dataValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProductControl
    {
        $data = $this->prepareData($data);

        $this->dataValidator->validate($data);

        return DB::transaction(function () use ($data): ProductControl {
            return ProductControl::query()->create([
                'name' => $data['name'],
                'control_type' => $data['control_type'],
                'control_number' => $this->codeGenerator->generate(),
                'status' => ProductControl::STATUS_ACTIVE,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProductControl $control, array $data): ProductControl
    {
        if ($control->isArchived()) {
            abort(404);
        }

        $data = $this->prepareData($data);

        $this->dataValidator->validate($data, $control);

        return DB::transaction(function () use ($control, $data): ProductControl {
            $control->update([
                'name' => $data['name'],
                'control_type' => $data['control_type'],
            ]);

            return $control->fresh();
        });
    }

    public function archive(ProductControl $control): void
    {
        if (! ProductControlAuthorization::canArchive()) {
            abort(403);
        }

        if ($control->isArchived()) {
            return;
        }

        $control->update([
            'status' => ProductControl::STATUS_ARCHIVED,
        ]);
    }

    public function restore(ProductControl $control): ProductControl
    {
        if (! ProductControlAuthorization::canRestore()) {
            abort(403);
        }

        if ($control->isArchived()) {
            $control->update([
                'status' => ProductControl::STATUS_ACTIVE,
            ]);
        }

        return $control->fresh();
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

        return $data;
    }
}
