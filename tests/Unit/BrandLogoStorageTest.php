<?php

namespace Tests\Unit;

use App\Services\ProductCatalog\BrandLogoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandLogoStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_locates_logo_on_configured_disk(): void
    {
        Storage::fake('local');
        config(['product-catalog.brand_logo_disk' => 'local']);

        Storage::disk('local')->put('brands/honda.png', 'logo-bytes');

        $located = app(BrandLogoStorage::class)->locate('brands/honda.png');

        $this->assertSame(['disk' => 'local', 'path' => 'brands/honda.png'], $located);
    }

    public function test_locates_logo_with_brands_prefix_fallback(): void
    {
        Storage::fake('local');
        config(['product-catalog.brand_logo_disk' => 'local']);

        Storage::disk('local')->put('brands/honda.png', 'logo-bytes');

        $located = app(BrandLogoStorage::class)->locate('honda.png');

        $this->assertSame(['disk' => 'local', 'path' => 'brands/honda.png'], $located);
    }
}
