<?php

namespace App\Services\ProductCatalog;

use App\Models\Attribute;
use App\Support\ProductCatalog\AttributeAuthorization;
use Illuminate\Support\Facades\DB;

class AttributePersistenceService
{
    public function __construct(
        protected AttributeCodeGenerator $codeGenerator,
        protected AttributeDataValidator $dataValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Attribute
    {
        $data = $this->prepareData($data);

        $this->dataValidator->validate($data);

        return DB::transaction(function () use ($data): Attribute {
            return Attribute::query()->create([
                'name' => $data['name'],
                'attribute_number' => $this->codeGenerator->generate(),
                'status' => Attribute::STATUS_ACTIVE,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Attribute $attribute, array $data): Attribute
    {
        if ($attribute->isArchived()) {
            abort(404);
        }

        $data = $this->prepareData($data);

        $this->dataValidator->validate($data, $attribute);

        return DB::transaction(function () use ($attribute, $data): Attribute {
            $attribute->update([
                'name' => $data['name'],
            ]);

            return $attribute->fresh();
        });
    }

    public function archive(Attribute $attribute): void
    {
        if (! AttributeAuthorization::canArchive()) {
            abort(403);
        }

        if ($attribute->isArchived()) {
            return;
        }

        $attribute->update([
            'status' => Attribute::STATUS_ARCHIVED,
        ]);
    }

    public function restore(Attribute $attribute): Attribute
    {
        if (! AttributeAuthorization::canRestore()) {
            abort(403);
        }

        if ($attribute->isArchived()) {
            $attribute->update([
                'status' => Attribute::STATUS_ACTIVE,
            ]);
        }

        return $attribute->fresh();
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
