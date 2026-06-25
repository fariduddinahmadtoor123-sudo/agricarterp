<?php

namespace App\Services\ProductCatalog;

use App\Models\Category;
use App\Rules\UniqueCategoryEnglishName;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryDataValidator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, ?Category $category = null): void
    {
        $validator = Validator::make($data, [
            'name_en' => ['required', 'string', 'max:255', new UniqueCategoryEnglishName($category)],
            'name_ur' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'image' => ['nullable'],
            'description_en' => ['nullable', 'string'],
            'description_ur' => ['nullable', 'string'],
            'short_description_en' => ['nullable', 'string'],
            'short_description_ur' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'seo_keywords' => ['nullable', 'string'],
            'seo_focus_keyword' => ['nullable', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($category?->id),
            ],
            'search_terms' => ['nullable', 'array'],
            'hs_code' => ['nullable', 'string', 'max:20'],
            'usage_en' => ['nullable', 'string'],
            'usage_ur' => ['nullable', 'string'],
            'benefits_en' => ['nullable', 'string'],
            'benefits_ur' => ['nullable', 'string'],
            'warnings_en' => ['nullable', 'string'],
            'warnings_ur' => ['nullable', 'string'],
            'import_export_notes_en' => ['nullable', 'string'],
            'import_export_notes_ur' => ['nullable', 'string'],
            'faqs_en' => ['nullable', 'array'],
            'faqs_ur' => ['nullable', 'array'],
            'buying_guide_en' => ['nullable', 'string'],
            'buying_guide_ur' => ['nullable', 'string'],
            'common_applications_en' => ['nullable', 'string'],
            'common_applications_ur' => ['nullable', 'string'],
            'customs_notes_en' => ['nullable', 'string'],
            'customs_notes_ur' => ['nullable', 'string'],
            'import_notes_en' => ['nullable', 'string'],
            'import_notes_ur' => ['nullable', 'string'],
            'export_notes_en' => ['nullable', 'string'],
            'export_notes_ur' => ['nullable', 'string'],
            'ai_status' => [
                'nullable',
                Rule::in([
                    Category::AI_STATUS_PENDING,
                    Category::AI_STATUS_PROCESSING,
                    Category::AI_STATUS_COMPLETE,
                    Category::AI_STATUS_REVIEW,
                    Category::AI_STATUS_FAILED,
                ]),
            ],
            'ai_generated_at' => ['nullable', 'date'],
            'ai_version' => ['nullable', 'string', 'max:50'],
            'status' => [
                'nullable',
                Rule::in([Category::STATUS_ACTIVE, Category::STATUS_ARCHIVED]),
            ],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null) {
            $parent = Category::query()->find($parentId);

            if ($parent === null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'The selected parent category is invalid.',
                ]);
            }

            if ($parent->isArchived()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Cannot use an archived category as parent.',
                ]);
            }

            if ($category !== null && (int) $parentId === $category->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A category cannot be its own parent.',
                ]);
            }
        }
    }
}
