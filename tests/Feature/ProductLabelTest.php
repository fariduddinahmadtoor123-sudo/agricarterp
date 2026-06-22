<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProductCatalog\ProductLabelQrGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_label_qr_route_returns_svg(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/product-label-qr?code=PRD-000001')
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml');
    }

    public function test_product_label_qr_generator_rejects_invalid_code(): void
    {
        $generator = app(ProductLabelQrGenerator::class);

        $this->assertFalse($generator->isValidProductNumber('INVALID'));
        $this->assertTrue($generator->isValidProductNumber('PRD-000001'));
        $this->assertStringContainsString('<svg', $generator->svg('PRD-000001'));
    }
}
