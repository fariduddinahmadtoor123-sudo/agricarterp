<?php

namespace App\Services\ProductCatalog;

use App\Models\Attribute;
use App\Models\Product;
use App\Rules\UniqueProductEnglishNamePerBrand;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductDataValidator
{
    public function __construct(
        protected ProductCategoryQuery $categoryQuery,
        protected ProductCatalogMasterQuery $masterQuery,
        protected ProductControlGroupQuery $groupQuery,
        protected ProductControlQuery $controlQuery,
        protected ProductControlAssignmentService $controlAssignment,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, ?Product $product = null): void
    {
        $brandId = $data['brand_id'] ?? $product?->brand_id;

        $validator = Validator::make($data, [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'base_unit_id' => ['required', 'integer', 'exists:units,id'],
            'packing_unit_id' => ['required', 'integer', 'exists:units,id'],
            'packing_value' => ['required', 'numeric', 'gt:0'],
            'name_en' => ['required', 'string', 'max:500', new UniqueProductEnglishNamePerBrand($brandId, $product)],
            'name_ur' => ['nullable', 'string', 'max:500'],
            'required_quantity' => ['nullable', 'numeric', 'gte:0'],
            'alert_quantity' => ['nullable', 'numeric', 'gte:0'],
            'main_image' => [$product === null ? 'required' : 'nullable'],
            'display_category_ids' => ['nullable', 'array'],
            'display_category_ids.*' => ['integer', 'exists:categories,id'],
            'attribute_rows' => ['nullable', 'array'],
            'attribute_rows.*.attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'attribute_rows.*.value' => ['required', 'string', 'max:500'],
            'control_group_ids' => ['nullable', 'array'],
            'control_group_ids.*' => ['integer', 'exists:product_control_groups,id'],
            'individual_control_ids' => ['nullable', 'array'],
            'individual_control_ids.*' => ['integer', 'exists:product_controls,id'],
            'status' => [
                'nullable',
                Rule::in([Product::STATUS_ACTIVE, Product::STATUS_ARCHIVED]),
            ],
            'short_description_en' => ['nullable', 'string'],
            'short_description_ur' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'description_ur' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'seo_keywords' => ['nullable', 'string'],
            'seo_focus_keyword' => ['nullable', 'string', 'max:255'],
            'search_terms' => ['nullable', 'array'],
            'hs_code' => ['nullable', 'string', 'max:20'],
            'usage_en' => ['nullable', 'string'],
            'usage_ur' => ['nullable', 'string'],
            'ai_status' => [
                'nullable',
                Rule::in([
                    Product::AI_STATUS_PENDING,
                    Product::AI_STATUS_PROCESSING,
                    Product::AI_STATUS_COMPLETE,
                    Product::AI_STATUS_REVIEW,
                    Product::AI_STATUS_FAILED,
                ]),
            ],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $categoryId = (int) $data['category_id'];

        if (! $this->categoryQuery->isActiveLeaf($categoryId)) {
            throw ValidationException::withMessages([
                'category_id' => 'Primary category must be an active leaf category.',
            ]);
        }

        if ($this->masterQuery->filterAssignableBrandId((int) $data['brand_id']) === null) {
            throw ValidationException::withMessages([
                'brand_id' => 'Selected brand is invalid or archived.',
            ]);
        }

        $unitIds = [(int) $data['base_unit_id'], (int) $data['packing_unit_id']];

        if (count($this->masterQuery->filterAssignableUnitIds($unitIds)) !== 2) {
            throw ValidationException::withMessages([
                'base_unit_id' => 'Selected units are invalid or archived.',
            ]);
        }

        $displayIds = array_map(fn ($id): int => (int) $id, $data['display_category_ids'] ?? []);
        $assignableTags = $this->categoryQuery->filterAssignableDisplayTagIds($displayIds, $categoryId);

        if (count($assignableTags) !== count(array_unique($displayIds))) {
            throw ValidationException::withMessages([
                'display_category_ids' => 'One or more display categories are invalid, archived, or match the primary category.',
            ]);
        }

        $attributeIds = array_map(
            fn (array $row): int => (int) ($row['attribute_id'] ?? 0),
            $data['attribute_rows'] ?? [],
        );

        if (count($attributeIds) !== count(array_unique(array_filter($attributeIds)))) {
            throw ValidationException::withMessages([
                'attribute_rows' => 'Each attribute may only be assigned once per product.',
            ]);
        }

        foreach ($attributeIds as $attributeId) {
            if ($attributeId > 0 && ! Attribute::query()->active()->whereKey($attributeId)->exists()) {
                throw ValidationException::withMessages([
                    'attribute_rows' => 'One or more attributes are invalid or archived.',
                ]);
            }
        }

        $groupIds = array_map(fn ($id): int => (int) $id, $data['control_group_ids'] ?? []);
        $assignableGroups = $this->groupQuery->filterAssignableGroupIds($groupIds);

        if (count($assignableGroups) !== count(array_unique($groupIds))) {
            throw ValidationException::withMessages([
                'control_group_ids' => 'One or more control groups are invalid or archived.',
            ]);
        }

        $individualIds = array_map(fn ($id): int => (int) $id, $data['individual_control_ids'] ?? []);
        $dedupedIndividuals = $this->controlAssignment->deduplicatedIndividualControlIds($groupIds, $individualIds);
        $assignableControls = $this->controlQuery->filterAssignableIds($dedupedIndividuals);

        if (count($assignableControls) !== count($dedupedIndividuals)) {
            throw ValidationException::withMessages([
                'individual_control_ids' => 'One or more individual controls are invalid, archived, or already included in a selected group.',
            ]);
        }

        if ($product === null && blank($this->imageStoragePath($data['main_image'] ?? null))) {
            throw ValidationException::withMessages([
                'main_image' => 'Main product image is required.',
            ]);
        }
    }

    protected function imageStoragePath(mixed $value): ?string
    {
        return app(ProductImageStorage::class)->extractPath($value);
    }
}
