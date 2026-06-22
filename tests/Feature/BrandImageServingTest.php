<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProductCatalog\BrandLogoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandImageServingTest extends TestCase
{
    use RefreshDatabase;

    public function test_serves_brand_logo_for_authenticated_user(): void
    {
        Storage::fake('local');
        config(['product-catalog.brand_logo_disk' => 'local']);

        Storage::disk('local')->put('brands/test.png', 'logo-bytes');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/brand-images?path=brands/test.png');

        $response->assertOk();
    }

    public function test_logo_url_uses_serving_route_for_local_disk(): void
    {
        Storage::fake('local');
        config(['product-catalog.brand_logo_disk' => 'local']);

        Storage::disk('local')->put('brands/test.png', 'logo-bytes');

        $storage = app(BrandLogoStorage::class);

        $url = $storage->url('brands/test.png');

        $this->assertSame(
            route($storage->servingRouteName(), ['path' => 'brands/test.png']),
            $url,
        );
    }

    public function test_guest_cannot_access_brand_logo(): void
    {
        Storage::fake('local');
        config(['product-catalog.brand_logo_disk' => 'local']);

        Storage::disk('local')->put('brands/test.png', 'logo-bytes');

        $this->get('/admin/brand-images?path=brands/test.png')
            ->assertRedirect();
    }
}
