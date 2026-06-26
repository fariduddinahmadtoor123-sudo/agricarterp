<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\ProductCatalog\CategoryCatalogQuery;
use App\Services\ProductCatalog\ProductCatalogQuery;
use App\Support\Catalog\CategoryCatalogPresenter;
use App\Support\Catalog\ProductCatalogPresenter;
use Illuminate\View\View;

class CategoryCatalogController extends Controller
{
    public function __construct(
        protected CategoryCatalogQuery $catalogQuery,
        protected ProductCatalogQuery $productCatalogQuery,
        protected CategoryCatalogPresenter $presenter,
        protected ProductCatalogPresenter $productPresenter,
    ) {}

    public function index(): View
    {
        $categories = $this->catalogQuery->rootCategories();

        return view('catalog.index', [
            'categories' => $this->presenter->cards($categories),
            'products' => [],
            'breadcrumbs' => $this->presenter->breadcrumbs(),
            'title' => null,
            'subtitle' => null,
            'parentCategory' => null,
        ]);
    }

    public function show(int $categoryId): View
    {
        $category = Category::query()->active()->findOrFail($categoryId);

        $children = $this->catalogQuery->childrenOf($category->id);
        $breadcrumbs = $this->presenter->breadcrumbs($category);
        $products = $this->productPresenter->cards(
            $this->productCatalogQuery->productsForCategory($category),
        );

        if ($children->isEmpty()) {
            return view('catalog.leaf', [
                'category' => $this->presenter->detail($category),
                'products' => $products,
                'breadcrumbs' => $breadcrumbs,
            ]);
        }

        return view('catalog.index', [
            'categories' => $this->presenter->cards($children),
            'products' => $products,
            'breadcrumbs' => $breadcrumbs,
            'title' => $category->name_en,
            'subtitle' => filled($category->name_ur) ? $category->name_ur : $category->full_path,
            'parentCategory' => $category,
        ]);
    }
}
