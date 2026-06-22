<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\ProductCatalog\CategoryCatalogQuery;
use App\Support\Catalog\CategoryCatalogPresenter;
use Illuminate\View\View;

class CategoryCatalogController extends Controller
{
    public function __construct(
        protected CategoryCatalogQuery $catalogQuery,
        protected CategoryCatalogPresenter $presenter,
    ) {}

    public function index(): View
    {
        $categories = $this->catalogQuery->rootCategories();

        return view('catalog.index', [
            'categories' => $this->presenter->cards($categories),
            'breadcrumbs' => $this->presenter->breadcrumbs(),
            'title' => 'Category Catalog',
            'subtitle' => 'Browse product categories by hierarchy',
            'parentCategory' => null,
        ]);
    }

    public function show(int $categoryId): View
    {
        $category = Category::query()->active()->findOrFail($categoryId);

        $children = $this->catalogQuery->childrenOf($category->id);
        $breadcrumbs = $this->presenter->breadcrumbs($category);

        if ($children->isEmpty()) {
            return view('catalog.leaf', [
                'category' => $this->presenter->detail($category),
                'breadcrumbs' => $breadcrumbs,
            ]);
        }

        return view('catalog.index', [
            'categories' => $this->presenter->cards($children),
            'breadcrumbs' => $breadcrumbs,
            'title' => $category->name_en,
            'subtitle' => filled($category->name_ur) ? $category->name_ur : $category->full_path,
            'parentCategory' => $category,
        ]);
    }
}
