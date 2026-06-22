<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Services\ProductCatalog\CategoryImageStorage;
use App\Services\ProductCatalog\CategoryPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_index_shows_root_categories(): void
    {
        app(CategoryPersistenceService::class)->create([
            'name_en' => 'Agriculture Machinery',
            'name_ur' => 'زرعی مشینری',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Agriculture Machinery');
        $response->assertSee('Category Catalog');
    }

    public function test_catalog_drills_down_to_children(): void
    {
        $root = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Agriculture Machinery',
            'name_ur' => 'زرعی مشینری',
        ]);

        $child = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Rotavator',
            'name_ur' => 'روٹیویٹر',
            'parent_id' => $root->id,
        ]);

        $this->get(route('catalog.show', ['categoryId' => $root->id]))
            ->assertOk()
            ->assertSee('Rotavator');

        $this->get(route('catalog.show', ['categoryId' => $child->id]))
            ->assertOk()
            ->assertSee('Leaf category');
    }

    public function test_catalog_serves_category_image_publicly(): void
    {
        Storage::fake('local');
        config(['product-catalog.category_image_disk' => 'local']);

        Storage::disk('local')->put('categories/test.jpg', 'image-bytes');

        $this->get('/category-images?path=categories/test.jpg')
            ->assertOk();
    }

    public function test_catalog_image_url_uses_public_serving_route(): void
    {
        Storage::fake('local');
        config(['product-catalog.category_image_disk' => 'local']);

        Storage::disk('local')->put('categories/test.jpg', 'image-bytes');

        $url = app(CategoryImageStorage::class)->catalogUrl('categories/test.jpg');

        $this->assertSame(
            route('catalog.category-images', ['path' => 'categories/test.jpg']),
            $url,
        );
    }

    public function test_archived_categories_are_not_accessible(): void
    {
        $category = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Hidden Category',
            'name_ur' => 'چھپی',
        ]);

        $category->update(['status' => Category::STATUS_ARCHIVED]);

        $this->get(route('catalog.show', ['categoryId' => $category->id]))->assertNotFound();
    }
}
