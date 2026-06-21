<?php

namespace App\Services\ProductCatalog;

use App\Models\Category;
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
            'name_en' => ['required', 'string', 'max:255'],
            'name_ur' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'image' => ['nullable'],
            'description_en' => ['nullable', 'string'],
            'description_ur' => ['nullable', 'string'],
            'short_description_en' => ['nullable', 'string'],
            'short_description_ur' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'seo_keywords' => ['nullable', 'string'],
            'hs_code' => ['nullable', 'string', 'max:20'],
            'usage_en' => ['nullable', 'string'],
            'usage_ur' => ['nullable', 'string'],
            'benefits_en' => ['nullable', 'string'],
            'benefits_ur' => ['nullable', 'string'],
            'warnings_en' => ['nullable', 'string'],
            'warnings_ur' => ['nullable', 'string'],
            'import_export_notes_en' => ['nullable', 'string'],
            'import_export_notes_ur' => ['nullable', 'string'],
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
