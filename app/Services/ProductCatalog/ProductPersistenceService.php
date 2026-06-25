<?php

namespace App\Services\ProductCatalog;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Support\ProductCatalog\ProductAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductPersistenceService
{
    public function __construct(
        protected ProductCodeGenerator $codeGenerator,
        protected ProductDataValidator $dataValidator,
        protected ProductImageStorage $imageStorage,
        protected ProductCategoryQuery $categoryQuery,
        protected ProductControlGroupQuery $groupQuery,
        protected ProductControlQuery $controlQuery,
        protected ProductControlAssignmentService $controlAssignment,
        protected ProductCategoryCountService $categoryCount,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        $data = $this->prepareData($data);

        $this->dataValidator->validate($data);

        return DB::transaction(function () use ($data): Product {
            $product = Product::query()->create([
                ...$this->contentAttributes($data),
                'product_number' => $this->codeGenerator->generate(),
                'status' => Product::STATUS_ACTIVE,
            ]);

            $this->syncImages($product, $data);
            $this->syncAttributeValues($product, $data['attribute_rows'] ?? []);
            $this->syncDisplayCategories($product, $data['display_category_ids'] ?? []);
            $this->syncControls($product, $data);

            $product = $product->fresh([
                'category',
                'brand',
                'baseUnit',
                'packingUnit',
                'images',
                'attributeValues',
                'categoryTags',
                'controlGroups',
                'individualControls',
            ]);

            $this->categoryCount->registerActiveProduct($product);

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        if ($product->isArchived()) {
            abort(404);
        }

        $data = $this->prepareData($data, $product);

        $this->dataValidator->validate($data, $product);

        return DB::transaction(function () use ($product, $data): Product {
            $wasActive = $product->isActive();
            $previousListingIds = $this->categoryCount->listingCategoryIds($product);

            $product->update($this->contentAttributes($data, $product));

            $this->syncImages($product, $data);
            $this->syncAttributeValues($product, $data['attribute_rows'] ?? []);
            $this->syncDisplayCategories($product, $data['display_category_ids'] ?? []);
            $this->syncControls($product, $data);

            $product = $product->fresh([
                'category',
                'brand',
                'baseUnit',
                'packingUnit',
                'images',
                'attributeValues',
                'categoryTags',
                'controlGroups',
                'individualControls',
            ]);

            $this->categoryCount->reconcileListingChange($product, $previousListingIds, $wasActive);

            return $product;
        });
    }

    public function archive(Product $product): void
    {
        if (! ProductAuthorization::canArchive()) {
            abort(403);
        }

        if ($product->isArchived()) {
            return;
        }

        DB::transaction(function () use ($product): void {
            $this->categoryCount->unregisterActiveProduct($product);

            $product->update([
                'status' => Product::STATUS_ARCHIVED,
            ]);
        });
    }

    public function restore(Product $product): Product
    {
        if (! ProductAuthorization::canRestore()) {
            abort(403);
        }

        return DB::transaction(function () use ($product): Product {
            if ($product->isArchived()) {
                $product->update([
                    'status' => Product::STATUS_ACTIVE,
                ]);

                $product = $product->fresh(['categoryTags']);
                $this->categoryCount->registerActiveProduct($product);
            }

            return $product->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data, ?Product $product = null): array
    {
        if (array_key_exists('name_en', $data) && is_string($data['name_en'])) {
            $data['name_en'] = trim($data['name_en']);
        }

        if (array_key_exists('display_category_ids', $data) && is_array($data['display_category_ids'])) {
            $data['display_category_ids'] = array_values(array_unique(array_map(
                fn ($id): int => (int) $id,
                array_filter($data['display_category_ids']),
            )));
        }

        if (array_key_exists('control_group_ids', $data) && is_array($data['control_group_ids'])) {
            $data['control_group_ids'] = array_values(array_unique(array_map(
                fn ($id): int => (int) $id,
                array_filter($data['control_group_ids']),
            )));
        }

        if (array_key_exists('individual_control_ids', $data) && is_array($data['individual_control_ids'])) {
            $data['individual_control_ids'] = array_values(array_unique(array_map(
                fn ($id): int => (int) $id,
                array_filter($data['individual_control_ids']),
            )));
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function contentAttributes(array $data, ?Product $product = null): array
    {
        return [
            'category_id' => (int) $data['category_id'],
            'brand_id' => (int) $data['brand_id'],
            'base_unit_id' => (int) $data['base_unit_id'],
            'packing_unit_id' => (int) $data['packing_unit_id'],
            'packing_value' => $data['packing_value'],
            'name_en' => $data['name_en'],
            'name_ur' => array_key_exists('name_ur', $data)
                ? (filled($data['name_ur'] ?? null) ? $data['name_ur'] : '')
                : ($product?->name_ur ?? ''),
            'required_quantity' => $data['required_quantity'] ?? 0,
            'alert_quantity' => $data['alert_quantity'] ?? 0,
            'wholesale_from_qty' => $data['wholesale_from_qty'] ?? 0,
            'super_wholesale_from_qty' => $data['super_wholesale_from_qty'] ?? 0,
            'distributor_from_qty' => $data['distributor_from_qty'] ?? 0,
            'short_description_en' => $data['short_description_en'] ?? null,
            'short_description_ur' => $data['short_description_ur'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'description_ur' => $data['description_ur'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'seo_keywords' => $data['seo_keywords'] ?? null,
            'seo_focus_keyword' => $data['seo_focus_keyword'] ?? null,
            'search_terms' => $data['search_terms'] ?? null,
            'hs_code' => $data['hs_code'] ?? null,
            'usage_en' => $data['usage_en'] ?? null,
            'usage_ur' => $data['usage_ur'] ?? null,
            'ai_status' => $data['ai_status'] ?? $product?->ai_status ?? Product::AI_STATUS_PENDING,
            'ai_generated_at' => array_key_exists('ai_generated_at', $data)
                ? $data['ai_generated_at']
                : $product?->ai_generated_at,
            'ai_version' => array_key_exists('ai_version', $data)
                ? $data['ai_version']
                : $product?->ai_version,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncImages(Product $product, array $data): void
    {
        $mainPath = $this->imageStorage->extractPath($data['main_image'] ?? null);

        if ($mainPath === null && $product->images()->where('is_main', true)->doesntExist()) {
            throw ValidationException::withMessages([
                'main_image' => 'Main product image is required.',
            ]);
        }

        $existingMain = $product->images()->where('is_main', true)->first();

        if ($mainPath !== null) {
            if ($existingMain !== null) {
                $this->imageStorage->cleanupIfReplaced($existingMain->image_path, $mainPath);
                $existingMain->update([
                    'image_path' => $mainPath,
                    'sort_order' => 0,
                ]);
            } else {
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'image_path' => $mainPath,
                    'is_main' => true,
                    'sort_order' => 0,
                ]);
            }
        }

        $additionalRows = $data['additional_images'] ?? [];
        $incomingPaths = [];
        $sortOrder = 10;

        foreach ($additionalRows as $row) {
            $path = $this->imageStorage->extractPath(is_array($row) ? ($row['image'] ?? null) : $row);

            if (blank($path)) {
                continue;
            }

            $incomingPaths[] = $path;

            $existing = $product->images()
                ->where('is_main', false)
                ->where('image_path', $path)
                ->first();

            if ($existing !== null) {
                $existing->update(['sort_order' => $sortOrder]);
            } else {
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_main' => false,
                    'sort_order' => $sortOrder,
                ]);
            }

            $sortOrder += 10;
        }

        $product->images()
            ->where('is_main', false)
            ->get()
            ->each(function (ProductImage $image) use ($incomingPaths): void {
                if (! in_array($image->image_path, $incomingPaths, true)) {
                    $this->imageStorage->deleteIfExists($image->image_path);
                    $image->delete();
                }
            });
    }

    /**
     * @param  list<array{attribute_id: int|string, value: string}>  $rows
     */
    protected function syncAttributeValues(Product $product, array $rows): void
    {
        $product->attributeValues()->delete();

        $sortOrder = 10;

        foreach ($rows as $row) {
            $attributeId = (int) ($row['attribute_id'] ?? 0);
            $value = trim((string) ($row['value'] ?? ''));

            if ($attributeId <= 0 || $value === '') {
                continue;
            }

            ProductAttributeValue::query()->create([
                'product_id' => $product->id,
                'attribute_id' => $attributeId,
                'value' => $value,
                'sort_order' => $sortOrder,
            ]);

            $sortOrder += 10;
        }
    }

    /**
     * @param  list<int>  $categoryIds
     */
    protected function syncDisplayCategories(Product $product, array $categoryIds): void
    {
        $assignable = $this->categoryQuery->filterAssignableDisplayTagIds(
            $categoryIds,
            (int) $product->category_id,
        );

        $sync = [];

        foreach ($assignable as $index => $categoryId) {
            $sync[$categoryId] = ['sort_order' => ($index + 1) * 10];
        }

        $product->categoryTags()->sync($sync);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncControls(Product $product, array $data): void
    {
        $groupIds = $this->groupQuery->filterAssignableGroupIds($data['control_group_ids'] ?? []);
        $individualIds = $this->controlAssignment->deduplicatedIndividualControlIds(
            $groupIds,
            $data['individual_control_ids'] ?? [],
        );
        $assignableIndividuals = $this->controlQuery->filterAssignableIds($individualIds);

        $groupSync = [];

        foreach ($groupIds as $index => $groupId) {
            $groupSync[$groupId] = ['sort_order' => ($index + 1) * 10];
        }

        $product->controlGroups()->sync($groupSync);

        $controlSync = [];

        foreach ($assignableIndividuals as $index => $controlId) {
            $controlSync[$controlId] = [
                'assignment_source' => 'individual',
                'sort_order' => ($index + 1) * 10,
            ];
        }

        $product->individualControls()->sync($controlSync);
    }
}
