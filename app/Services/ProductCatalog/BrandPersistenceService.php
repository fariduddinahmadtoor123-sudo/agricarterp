<?php

namespace App\Services\ProductCatalog;

use App\Models\Brand;
use App\Support\ProductCatalog\BrandAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BrandPersistenceService
{
    public function __construct(
        protected BrandCodeGenerator $codeGenerator,
        protected BrandDataValidator $dataValidator,
        protected BrandLogoStorage $logoStorage,
        protected BrandCategoryQuery $categoryQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Brand
    {
        $data = $this->prepareData($data);

        $this->dataValidator->validate($data);

        return DB::transaction(function () use ($data): Brand {
            $brand = Brand::query()->create([
                ...$this->contentAttributes($data),
                'brand_number' => $this->codeGenerator->generate(),
                'status' => Brand::STATUS_ACTIVE,
                'categories_count' => 0,
            ]);

            $this->syncCategories($brand, $data['category_ids'] ?? []);

            return $brand->fresh(['categories']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Brand $brand, array $data): Brand
    {
        if ($brand->isArchived()) {
            abort(404);
        }

        $data = $this->prepareData($data, $brand);

        $this->dataValidator->validate($data, $brand);

        return DB::transaction(function () use ($brand, $data): Brand {
            $brand->update($this->contentAttributes($data, $brand));

            if (array_key_exists('category_ids', $data)) {
                $this->syncCategories($brand, $data['category_ids'] ?? []);
            }

            return $brand->fresh(['categories']);
        });
    }

    public function archive(Brand $brand): void
    {
        if (! BrandAuthorization::canArchive()) {
            abort(403);
        }

        if ($brand->isArchived()) {
            return;
        }

        $brand->update([
            'status' => Brand::STATUS_ARCHIVED,
        ]);
    }

    public function restore(Brand $brand): Brand
    {
        if (! BrandAuthorization::canRestore()) {
            abort(403);
        }

        if ($brand->isArchived()) {
            $brand->update([
                'status' => Brand::STATUS_ACTIVE,
            ]);
        }

        return $brand->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data, ?Brand $brand = null): array
    {
        if (array_key_exists('name_en', $data) && is_string($data['name_en'])) {
            $data['name_en'] = trim($data['name_en']);
        }

        if (array_key_exists('category_ids', $data) && is_array($data['category_ids'])) {
            $data['category_ids'] = array_values(array_unique(array_map(
                fn ($id): int => (int) $id,
                array_filter($data['category_ids']),
            )));
        }

        if (array_key_exists('logo', $data)) {
            $newPath = $this->logoStorage->extractPath($data['logo']);

            if ($brand !== null) {
                $this->logoStorage->cleanupIfReplaced($brand->logo_path, $newPath);
            }

            $data['logo_path'] = $newPath;
        }

        return $data;
    }

    /**
     * @param  list<int>  $categoryIds
     */
    protected function syncCategories(Brand $brand, array $categoryIds): void
    {
        $assignableIds = $this->categoryQuery->filterAssignableIds($categoryIds);

        $brand->categories()->sync($assignableIds);

        $brand->update([
            'categories_count' => count($assignableIds),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function contentAttributes(array $data, ?Brand $brand = null): array
    {
        $attributes = [
            'name_en' => $data['name_en'],
            'name_ur' => filled($data['name_ur'] ?? null) ? $data['name_ur'] : '',
            'short_note' => $data['short_note'],
            'short_description_en' => $data['short_description_en'] ?? null,
            'short_description_ur' => $data['short_description_ur'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'description_ur' => $data['description_ur'] ?? null,
            'brand_overview_en' => $data['brand_overview_en'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'seo_keywords' => $data['seo_keywords'] ?? null,
            'country' => $data['country'] ?? null,
            'website' => $data['website'] ?? null,
            'ai_status' => $data['ai_status'] ?? $brand?->ai_status ?? Brand::AI_STATUS_PENDING,
            'ai_generated_at' => array_key_exists('ai_generated_at', $data)
                ? $data['ai_generated_at']
                : $brand?->ai_generated_at,
            'ai_version' => array_key_exists('ai_version', $data)
                ? $data['ai_version']
                : $brand?->ai_version,
        ];

        if (array_key_exists('logo_path', $data)) {
            $attributes['logo_path'] = $data['logo_path'];
        }

        return $attributes;
    }
}
