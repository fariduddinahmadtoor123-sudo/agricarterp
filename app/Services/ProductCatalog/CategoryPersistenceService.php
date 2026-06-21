<?php

namespace App\Services\ProductCatalog;

use App\Models\Category;
use App\Support\ProductCatalog\CategoryAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryPersistenceService
{
    public function __construct(
        protected CategoryCodeGenerator $codeGenerator,
        protected CategoryDataValidator $dataValidator,
        protected CategoryHierarchyService $hierarchy,
        protected CategoryImageStorage $imageStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category
    {
        $data = $this->prepareData($data);

        $this->dataValidator->validate($data);

        return DB::transaction(function () use ($data): Category {
            $parentId = $data['parent_id'] ?? null;
            $parent = $parentId ? Category::query()->find($parentId) : null;
            $sortOrder = $this->hierarchy->nextSortOrder($parentId);

            $category = Category::query()->create([
                ...$this->contentAttributes($data),
                'parent_id' => $parentId,
                'category_number' => $this->codeGenerator->generate(),
                'sort_order' => $sortOrder,
                'level' => 0,
                'visual_mapping_code' => '',
                'full_path' => '',
                'is_leaf' => true,
                'status' => Category::STATUS_ACTIVE,
                'products_count' => 0,
            ]);

            $this->hierarchy->rebuildSubtree($category);

            if ($parentId !== null) {
                $this->hierarchy->refreshLeafStatus($parentId);
            }

            return $category->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        if ($category->isArchived()) {
            abort(404);
        }

        $data = $this->prepareData($data, $category);

        $this->dataValidator->validate($data, $category);

        $newParentId = $data['parent_id'] ?? null;
        $parentChanged = (int) ($category->parent_id ?? 0) !== (int) ($newParentId ?? 0);

        if ($parentChanged) {
            return $this->move($category, $newParentId, $data);
        }

        return DB::transaction(function () use ($category, $data): Category {
            $nameChanged = $category->name_en !== $data['name_en'];

            $category->update($this->contentAttributes($data, $category));

            if ($nameChanged) {
                $this->hierarchy->rebuildSubtree($category->fresh());
            }

            return $category->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function move(Category $category, ?int $newParentId, array $data = []): Category
    {
        if ($category->isArchived()) {
            abort(404);
        }

        if ($newParentId !== null) {
            $newParent = Category::query()->findOrFail($newParentId);

            if ($newParent->isArchived()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Cannot move a category under an archived parent.',
                ]);
            }

            if ($newParent->id === $category->id || $this->hierarchy->isDescendantOf($newParent, $category->id)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Cannot move a category under itself or its descendant.',
                ]);
            }
        }

        if ($data !== []) {
            $data = $this->prepareData($data, $category);
            $this->dataValidator->validate($data, $category);
        }

        return DB::transaction(function () use ($category, $newParentId, $data): Category {
            $oldParentId = $category->parent_id;

            if ($data !== []) {
                $category->update($this->contentAttributes($data, $category));
            }

            $category->update([
                'parent_id' => $newParentId,
                'sort_order' => $this->hierarchy->nextSortOrder($newParentId),
            ]);

            $this->hierarchy->rebuildSubtree($category->fresh());

            $this->hierarchy->refreshLeafStatus($oldParentId);
            $this->hierarchy->refreshLeafStatus($newParentId);
            $this->hierarchy->refreshLeafStatus($category->id);

            return $category->fresh();
        });
    }

    public function archive(Category $category): void
    {
        if (! CategoryAuthorization::canArchive()) {
            abort(403);
        }

        if ($category->isArchived()) {
            return;
        }

        $category->update([
            'status' => Category::STATUS_ARCHIVED,
        ]);
    }

    public function restore(Category $category): Category
    {
        if (! CategoryAuthorization::canRestore()) {
            abort(403);
        }

        if ($category->isArchived()) {
            $category->update([
                'status' => Category::STATUS_ACTIVE,
            ]);
        }

        return $category->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data, ?Category $category = null): array
    {
        if (array_key_exists('parent_id', $data) && blank($data['parent_id'])) {
            $data['parent_id'] = null;
        }

        if (array_key_exists('image', $data)) {
            $newPath = $this->imageStorage->extractPath($data['image']);

            if ($category !== null) {
                $this->imageStorage->cleanupIfReplaced($category->image_path, $newPath);
            }

            $data['image_path'] = $newPath;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function contentAttributes(array $data, ?Category $category = null): array
    {
        $attributes = [
            'name_en' => $data['name_en'],
            'name_ur' => $data['name_ur'],
            'description_en' => $data['description_en'] ?? null,
            'description_ur' => $data['description_ur'] ?? null,
            'short_description_en' => $data['short_description_en'] ?? null,
            'short_description_ur' => $data['short_description_ur'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'seo_keywords' => $data['seo_keywords'] ?? null,
            'hs_code' => $data['hs_code'] ?? null,
            'usage_en' => $data['usage_en'] ?? null,
            'usage_ur' => $data['usage_ur'] ?? null,
            'benefits_en' => $data['benefits_en'] ?? null,
            'benefits_ur' => $data['benefits_ur'] ?? null,
            'warnings_en' => $data['warnings_en'] ?? null,
            'warnings_ur' => $data['warnings_ur'] ?? null,
            'import_export_notes_en' => $data['import_export_notes_en'] ?? null,
            'import_export_notes_ur' => $data['import_export_notes_ur'] ?? null,
        ];

        if (array_key_exists('image_path', $data)) {
            $attributes['image_path'] = $data['image_path'];
        }

        if (array_key_exists('parent_id', $data)) {
            $attributes['parent_id'] = $data['parent_id'];
        }

        return $attributes;
    }
}
