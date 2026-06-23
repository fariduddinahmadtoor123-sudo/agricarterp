<?php

namespace Tests\Feature;

use App\Filament\Pages\PurchasingInventory\ReOrderCenter;
use App\Filament\Pages\PurchasingInventory\ReOrderSendWorksheet;
use App\Models\PurchasingInventory\ReorderOrder;
use App\Models\User;
use App\Services\PurchasingInventory\ReOrderLineBuilder;
use App\Support\PurchasingInventory\ReOrderQueueRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReOrderCenterPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_reorder_center_page_loads_for_authenticated_user(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/purchasing-inventory/re-order-center')
            ->assertOk()
            ->assertSee('Needs Re-Order')
            ->assertSee('Order Queue');
    }

    public function test_send_worksheet_returns_404_for_missing_order(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/purchasing-inventory/re-order-center/orders/missing-id')
            ->assertNotFound();
    }

    public function test_queue_repository_creates_order_and_blocks_active_products(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $repository = app(ReOrderQueueRepository::class);
        $builder = app(ReOrderLineBuilder::class);

        $line = $builder->fromProduct([
            'id' => 42,
            'barcode' => '10042',
            'sku' => '10042',
            'name_en' => 'Sample Pump',
            'name_ur' => '',
            'thumbnail_url' => null,
            'required_quantity' => 20,
            'alert_quantity' => 5,
        ], 2);

        $order = $repository->createOrder('Usman', 'en', [$line]);

        $this->assertMatchesRegularExpression('/^RO-\d{8}-\d{4}$/', $order['order_number']);
        $this->assertSame('Usman', $order['purchaser_name']);
        $this->assertContains(42, $repository->activeProductIds());

        $found = $repository->findOrder($order['id']);
        $this->assertNotNull($found);
        $this->assertCount(1, $found['lines']);
    }

    public function test_mark_received_clears_active_block_without_stock_change(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $repository = app(ReOrderQueueRepository::class);
        $builder = app(ReOrderLineBuilder::class);

        $productId = 7;

        $line = $builder->fromProduct([
            'id' => $productId,
            'barcode' => '10007',
            'sku' => '10007',
            'name_en' => 'Filter Mesh',
            'name_ur' => '',
            'thumbnail_url' => null,
            'required_quantity' => 10,
            'alert_quantity' => 3,
        ], 1);

        $order = $repository->createOrder('Purchaser A', 'both', [$line]);
        $repository->markReceived($order['id']);

        $this->assertNotContains($productId, $repository->activeProductIds());
        $this->assertSame('received', $repository->findOrder($order['id'])['status']);
    }

    public function test_stale_orders_are_flagged_after_threshold(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $repository = app(ReOrderQueueRepository::class);
        $builder = app(ReOrderLineBuilder::class);

        $line = $builder->fromProduct([
            'id' => 9,
            'barcode' => '10009',
            'sku' => '10009',
            'name_en' => 'Hose Pipe',
            'name_ur' => '',
            'thumbnail_url' => null,
            'required_quantity' => 5,
            'alert_quantity' => 2,
        ], 0);

        $order = $repository->createOrder('Purchaser B', 'ur', [$line]);

        ReorderOrder::query()->whereKey($order['id'])->update([
            'sent_at' => now()->subDays(10),
        ]);

        $repository->flagStaleOrders();

        $this->assertSame('stale', $repository->findOrder($order['id'])['status']);
    }

    public function test_send_worksheet_loads_for_existing_order(): void
    {
        $user = User::factory()->superAdmin()->create();
        $repository = app(ReOrderQueueRepository::class);
        $builder = app(ReOrderLineBuilder::class);

        $line = $builder->fromProduct([
            'id' => 3,
            'barcode' => '10003',
            'sku' => '10003',
            'name_en' => 'Nozzle',
            'name_ur' => 'نوزل',
            'thumbnail_url' => null,
            'required_quantity' => 8,
            'alert_quantity' => 2,
        ], 1);

        $order = $repository->createOrder('Ali', 'both', [$line]);

        $this->actingAs($user)
            ->get('/admin/purchasing-inventory/re-order-center/orders/' . $order['id'])
            ->assertOk()
            ->assertSee('Purchaser Re-Order')
            ->assertSee('Market Rate 1')
            ->assertSee('Nozzle');
    }
}
