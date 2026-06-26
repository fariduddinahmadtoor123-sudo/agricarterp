<?php

namespace Tests\Feature;

use App\Models\SalesPos\PosSale;
use App\Models\User;
use App\Support\SalesPos\PosSaleRepository;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HeldSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_held_sales_page_loads(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/sales-pos/held-sales')
            ->assertOk();
    }

    public function test_held_sales_list_shows_only_held_records(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $repository = app(PosSaleRepository::class);
        $held = $repository->create(['customer_name' => 'Held Customer']);
        $held['status'] = PosSale::STATUS_HELD;
        $held['held_label'] = 'Counter 1';
        $repository->update($held);

        $draft = $repository->create(['customer_name' => 'Draft Customer']);

        $this->get('/admin/sales-pos/held-sales')
            ->assertOk()
            ->assertSee('Held Customer')
            ->assertDontSee('Draft Customer');
    }
}
