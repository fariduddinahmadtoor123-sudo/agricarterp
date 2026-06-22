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

    public function test_leaf_category_page_shows_assigned_products(): void
    {
        Storage::fake('local');
        config(['product-catalog.product_image_disk' => 'local']);

        $leaf = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Rotor Gear',
            'name_ur' => '',
            'parent_id' => app(CategoryPersistenceService::class)->create([
                'name_en' => 'Rotavator',
                'name_ur' => '',
                'parent_id' => app(CategoryPersistenceService::class)->create([
                    'name_en' => 'Agriculture',
                    'name_ur' => '',
                ])->id,
            ])->id,
        ]);

        $brand = app(\App\Services\ProductCatalog\BrandPersistenceService::class)->create([
            'name_en' => 'Honda',
            'short_note' => 'Test',
            'category_ids' => [],
        ]);

        $piece = app(\App\Services\ProductCatalog\UnitPersistenceService::class)->create([
            'name_en' => 'Piece',
            'abbreviation_en' => 'pcs',
            'unit_type' => \App\Models\Unit::TYPE_COUNT,
        ]);

        $pack = app(\App\Services\ProductCatalog\UnitPersistenceService::class)->create([
            'name_en' => 'Pack',
            'abbreviation_en' => 'pk',
            'unit_type' => \App\Models\Unit::TYPE_COUNT,
        ]);

        $path = 'products/test.jpg';
        Storage::disk('local')->put($path, 'image-bytes');

        $product = app(\App\Services\ProductCatalog\ProductPersistenceService::class)->create([
            'category_id' => $leaf->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $piece->id,
            'packing_unit_id' => $pack->id,
            'packing_value' => 1,
            'name_en' => 'Catalog Test Pump',
            'main_image' => $path,
            'additional_images' => [],
            'display_category_ids' => [],
            'attribute_rows' => [],
            'control_group_ids' => [],
            'individual_control_ids' => [],
        ]);

        $this->get(route('catalog.show', ['categoryId' => $leaf->id]))
            ->assertOk()
            ->assertSee('Products')
            ->assertSee('Catalog Test Pump')
            ->assertSee($product->product_number);
    }

    public function test_parent_category_page_shows_display_tagged_products(): void
    {
        Storage::fake('local');
        config(['product-catalog.product_image_disk' => 'local']);

        $parent = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Agriculture',
            'name_ur' => '',
        ]);

        $displayCategory = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Featured Pumps',
            'name_ur' => '',
            'parent_id' => $parent->id,
        ]);

        $leaf = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Leaf Pump',
            'name_ur' => '',
            'parent_id' => $parent->id,
        ]);

        $brand = app(\App\Services\ProductCatalog\BrandPersistenceService::class)->create([
            'name_en' => 'Honda',
            'short_note' => 'Test',
            'category_ids' => [],
        ]);

        $piece = app(\App\Services\ProductCatalog\UnitPersistenceService::class)->create([
            'name_en' => 'Piece',
            'abbreviation_en' => 'pcs',
            'unit_type' => \App\Models\Unit::TYPE_COUNT,
        ]);

        $pack = app(\App\Services\ProductCatalog\UnitPersistenceService::class)->create([
            'name_en' => 'Pack',
            'abbreviation_en' => 'pk',
            'unit_type' => \App\Models\Unit::TYPE_COUNT,
        ]);

        $path = 'products/test.jpg';
        Storage::disk('local')->put($path, 'image-bytes');

        app(\App\Services\ProductCatalog\ProductPersistenceService::class)->create([
            'category_id' => $leaf->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $piece->id,
            'packing_unit_id' => $pack->id,
            'packing_value' => 1,
            'name_en' => 'Tagged Pump Product',
            'main_image' => $path,
            'additional_images' => [],
            'display_category_ids' => [$displayCategory->id],
            'attribute_rows' => [],
            'control_group_ids' => [],
            'individual_control_ids' => [],
        ]);

        $this->get(route('catalog.show', ['categoryId' => $displayCategory->id]))
            ->assertOk()
            ->assertSee('Tagged Pump Product');
    }

    public function test_catalog_serves_product_image_publicly(): void
    {
        Storage::fake('local');
        config(['product-catalog.product_image_disk' => 'local']);

        Storage::disk('local')->put('products/test.jpg', 'image-bytes');

        $this->get('/product-images?path=products/test.jpg')
            ->assertOk();
    }

    public function test_root_and_parent_category_cards_show_subtree_product_counts(): void
    {
        Storage::fake('local');
        config(['product-catalog.product_image_disk' => 'local']);

        $root = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Agriculture',
            'name_ur' => '',
        ]);

        $parent = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Rotavator',
            'name_ur' => '',
            'parent_id' => $root->id,
        ]);

        $leaf = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Rotor Gear',
            'name_ur' => '',
            'parent_id' => $parent->id,
        ]);

        $brand = app(\App\Services\ProductCatalog\BrandPersistenceService::class)->create([
            'name_en' => 'Honda',
            'short_note' => 'Test',
            'category_ids' => [],
        ]);

        $piece = app(\App\Services\ProductCatalog\UnitPersistenceService::class)->create([
            'name_en' => 'Piece',
            'abbreviation_en' => 'pcs',
            'unit_type' => \App\Models\Unit::TYPE_COUNT,
        ]);

        $pack = app(\App\Services\ProductCatalog\UnitPersistenceService::class)->create([
            'name_en' => 'Pack',
            'abbreviation_en' => 'pk',
            'unit_type' => \App\Models\Unit::TYPE_COUNT,
        ]);

        $path = 'products/test.jpg';
        Storage::disk('local')->put($path, 'image-bytes');

        app(\App\Services\ProductCatalog\ProductPersistenceService::class)->create([
            'category_id' => $leaf->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $piece->id,
            'packing_unit_id' => $pack->id,
            'packing_value' => 1,
            'name_en' => 'Subtree Count Pump',
            'main_image' => $path,
            'additional_images' => [],
            'display_category_ids' => [],
            'attribute_rows' => [],
            'control_group_ids' => [],
            'individual_control_ids' => [],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('1 products');

        $this->get(route('catalog.show', ['categoryId' => $root->id]))
            ->assertOk()
            ->assertSee('1 products')
            ->assertSee('Subtree Count Pump');

        $this->get(route('catalog.show', ['categoryId' => $parent->id]))
            ->assertOk()
            ->assertSee('1 products')
            ->assertSee('Subtree Count Pump');
    }
}
