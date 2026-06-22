<?php

namespace App\Support\Catalog;

use App\Models\Category;
use App\Services\ProductCatalog\CategoryHierarchyService;
use App\Services\ProductCatalog\CategoryImageStorage;
use Illuminate\Support\Collection;

class CategoryCatalogPresenter
{
    public function __construct(
        protected CategoryImageStorage $imageStorage,
        protected CategoryHierarchyService $hierarchy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function card(Category $category): array
    {
        return [
            'id' => $category->id,
            'name_en' => $category->name_en,
            'name_ur' => filled($category->name_ur) ? $category->name_ur : null,
            'category_number' => $category->category_number,
            'image_url' => $this->imageUrl($category->image_path),
            'products_count' => (int) $category->products_count,
            'children_count' => (int) ($category->children_count ?? 0),
            'url' => route('catalog.show', ['categoryId' => $category->id]),
            'is_leaf' => (bool) $category->is_leaf,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Category $category): array
    {
        return [
            ...$this->card($category),
            'full_path' => $category->full_path,
            'visual_mapping_code' => $category->visual_mapping_code,
            'level' => (int) $category->level,
            'short_description_en' => $category->short_description_en,
            'short_description_ur' => $category->short_description_ur,
            'description_en' => $category->description_en,
            'description_ur' => $category->description_ur,
            'hs_code' => $category->hs_code,
        ];
    }

    public function imageUrl(?string $path): ?string
    {
        return $this->imageStorage->catalogUrl($path);
    }

    /**
     * @return list<array{label: string, url: ?string}>
     */
    public function breadcrumbs(?Category $category = null): array
    {
        $crumbs = [
            ['label' => 'Home', 'url' => route('catalog.index')],
        ];

        if ($category === null) {
            return $crumbs;
        }

        foreach ($this->hierarchy->ancestors($category) as $ancestor) {
            $crumbs[] = [
                'label' => $ancestor->name_en,
                'url' => route('catalog.show', ['categoryId' => $ancestor->id]),
            ];
        }

        $crumbs[] = [
            'label' => $category->name_en,
            'url' => null,
        ];

        return $crumbs;
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return list<array<string, mixed>>
     */
    public function cards(Collection $categories): array
    {
        return $categories
            ->map(fn (Category $category): array => $this->card($category))
            ->all();
    }
}
