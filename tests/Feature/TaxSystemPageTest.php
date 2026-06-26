<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\TaxSystem;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaxSystemPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_system_page_loads_for_super_admin(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get('/admin/settings/tax-system')
            ->assertOk()
            ->assertSee('Tax System');

        Livewire::actingAs($user)
            ->test(TaxSystem::class)
            ->assertSee('Add Tax');
    }

    public function test_tax_table_lists_saved_records(): void
    {
        $user = User::factory()->superAdmin()->create();

        Tax::query()->create([
            'name' => 'Withholding Tax',
            'code' => 'WHT',
            'type' => Tax::TYPE_PERCENTAGE,
            'rate_value' => 4.5,
            'apply_on' => ['purchase'],
            'status' => Tax::STATUS_ACTIVE,
        ]);

        Livewire::actingAs($user)
            ->test(TaxSystem::class)
            ->assertSee('Withholding Tax')
            ->assertSee('WHT');
    }
}
