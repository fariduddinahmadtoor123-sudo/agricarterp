<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProductCatalog\CategoryImageStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryImageServingTest extends TestCase
{
    use RefreshDatabase;

    public function test_serves_category_image_for_authenticated_user(): void
    {
        Storage::fake('local');
        config(['product-catalog.category_image_disk' => 'local']);

        Storage::disk('local')->put('categories/test.jpg', 'image-bytes');

        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get('/admin/category-images?path=categories/test.jpg');

        $response->assertOk();
    }

    public function test_image_url_uses_serving_route_for_local_disk(): void
    {
        Storage::fake('local');
        config(['product-catalog.category_image_disk' => 'local']);

        Storage::disk('local')->put('categories/test.jpg', 'image-bytes');

        $storage = app(CategoryImageStorage::class);

        $url = $storage->url('categories/test.jpg');

        $this->assertSame(
            route($storage->servingRouteName(), ['path' => 'categories/test.jpg']),
            $url,
        );
    }

    public function test_guest_cannot_access_category_image(): void
    {
        Storage::fake('local');
        config(['product-catalog.category_image_disk' => 'local']);

        Storage::disk('local')->put('categories/test.jpg', 'image-bytes');

        $this->get('/admin/category-images?path=categories/test.jpg')
            ->assertRedirect();
    }
}
