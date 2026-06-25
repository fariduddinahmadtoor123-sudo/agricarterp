<?php

namespace App\Services\ProductCatalog;

use App\Models\Brand;
use App\Rules\UniqueBrandEnglishName;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BrandDataValidator
{
    public function __construct(
        protected BrandCategoryQuery $categoryQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, ?Brand $brand = null): void
    {
        $validator = Validator::make($data, [
            'name_en' => ['required', 'string', 'max:255', new UniqueBrandEnglishName($brand)],
            'short_note' => ['required', 'string'],
            'name_ur' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'short_description_en' => ['nullable', 'string'],
            'short_description_ur' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'description_ur' => ['nullable', 'string'],
            'brand_overview_en' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'seo_keywords' => ['nullable', 'string'],
            'country' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'string', 'max:500'],
            'ai_status' => [
                'nullable',
                Rule::in([
                    Brand::AI_STATUS_PENDING,
                    Brand::AI_STATUS_PROCESSING,
                    Brand::AI_STATUS_COMPLETE,
                    Brand::AI_STATUS_REVIEW,
                    Brand::AI_STATUS_FAILED,
                ]),
            ],
            'ai_generated_at' => ['nullable', 'date'],
            'ai_version' => ['nullable', 'string', 'max:50'],
            'status' => [
                'nullable',
                Rule::in([Brand::STATUS_ACTIVE, Brand::STATUS_ARCHIVED]),
            ],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $categoryIds = $data['category_ids'] ?? [];

        if ($categoryIds !== []) {
            $assignable = $this->categoryQuery->filterAssignableIds(
                array_map(fn ($id): int => (int) $id, $categoryIds),
            );

            if (count($assignable) !== count(array_unique($categoryIds))) {
                throw ValidationException::withMessages([
                    'category_ids' => 'One or more selected categories are invalid or archived.',
                ]);
            }
        }
    }
}
