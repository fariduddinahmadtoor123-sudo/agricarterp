<?php

namespace App\Services\ProductCatalog;

use App\Models\Unit;
use App\Support\ProductCatalog\UnitAuthorization;
use Illuminate\Support\Facades\DB;

class UnitPersistenceService
{
    public function __construct(
        protected UnitCodeGenerator $codeGenerator,
        protected UnitDataValidator $dataValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Unit
    {
        $data = $this->prepareData($data);

        $this->dataValidator->validate($data);

        return DB::transaction(function () use ($data): Unit {
            return Unit::query()->create([
                ...$this->contentAttributes($data),
                'unit_number' => $this->codeGenerator->generate(),
                'status' => Unit::STATUS_ACTIVE,
                'is_standard' => (bool) ($data['is_standard'] ?? false),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Unit $unit, array $data): Unit
    {
        if ($unit->isArchived()) {
            abort(404);
        }

        $data = $this->prepareData($data);

        $this->dataValidator->validate($data, $unit);

        return DB::transaction(function () use ($unit, $data): Unit {
            $unit->update($this->contentAttributes($data, $unit));

            return $unit->fresh();
        });
    }

    public function archive(Unit $unit): void
    {
        if (! UnitAuthorization::canArchive()) {
            abort(403);
        }

        if ($unit->isArchived()) {
            return;
        }

        $unit->update([
            'status' => Unit::STATUS_ARCHIVED,
        ]);
    }

    public function restore(Unit $unit): Unit
    {
        if (! UnitAuthorization::canRestore()) {
            abort(403);
        }

        if ($unit->isArchived()) {
            $unit->update([
                'status' => Unit::STATUS_ACTIVE,
            ]);
        }

        return $unit->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data, ?Unit $unit = null): array
    {
        if (array_key_exists('name_en', $data) && is_string($data['name_en'])) {
            $data['name_en'] = trim($data['name_en']);
        }

        if (array_key_exists('abbreviation_en', $data) && is_string($data['abbreviation_en'])) {
            $data['abbreviation_en'] = trim($data['abbreviation_en']);
        }

        if (array_key_exists('name_ur', $data) && is_string($data['name_ur'])) {
            $data['name_ur'] = trim($data['name_ur']);
        }

        if (array_key_exists('abbreviation_ur', $data) && is_string($data['abbreviation_ur'])) {
            $data['abbreviation_ur'] = trim($data['abbreviation_ur']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function contentAttributes(array $data, ?Unit $unit = null): array
    {
        return [
            'name_en' => $data['name_en'],
            'abbreviation_en' => $data['abbreviation_en'],
            'name_ur' => filled($data['name_ur'] ?? null) ? $data['name_ur'] : null,
            'abbreviation_ur' => filled($data['abbreviation_ur'] ?? null) ? $data['abbreviation_ur'] : null,
            'usage_notes' => $data['usage_notes'] ?? null,
            'unit_type' => $data['unit_type'],
            'ai_status' => $data['ai_status'] ?? $unit?->ai_status ?? Unit::AI_STATUS_PENDING,
            'ai_generated_at' => array_key_exists('ai_generated_at', $data)
                ? $data['ai_generated_at']
                : $unit?->ai_generated_at,
            'ai_version' => array_key_exists('ai_version', $data)
                ? $data['ai_version']
                : $unit?->ai_version,
        ];
    }
}
