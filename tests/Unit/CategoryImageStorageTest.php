<?php

namespace Tests\Unit;

use App\Services\ProductCatalog\CategoryImageStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryImageStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_locates_image_on_configured_disk(): void
    {
        Storage::fake('local');
        config(['product-catalog.category_image_disk' => 'local']);

        Storage::disk('local')->put('categories/pump.jpg', 'image-bytes');

        $located = app(CategoryImageStorage::class)->locate('categories/pump.jpg');

        $this->assertSame(['disk' => 'local', 'path' => 'categories/pump.jpg'], $located);
    }

    public function test_locates_image_with_categories_prefix_fallback(): void
    {
        Storage::fake('local');
        config(['product-catalog.category_image_disk' => 'local']);

        Storage::disk('local')->put('categories/pump.jpg', 'image-bytes');

        $located = app(CategoryImageStorage::class)->locate('pump.jpg');

        $this->assertSame(['disk' => 'local', 'path' => 'categories/pump.jpg'], $located);
    }

    public function test_catalog_url_returns_public_image_route(): void
    {
        Storage::fake('local');
        config(['product-catalog.category_image_disk' => 'local']);

        Storage::disk('local')->put('categories/pump.jpg', 'image-bytes');

        $url = app(CategoryImageStorage::class)->catalogUrl('categories/pump.jpg');

        $this->assertSame(
            route('catalog.category-images', ['path' => 'categories/pump.jpg']),
            $url,
        );
    }
}
