<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\Overview;
use App\Filament\Pages\Settings\System;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsOverviewPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_overview_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get('/admin/settings/overview')
            ->assertOk()
            ->assertSee('Settings Summary')
            ->assertSee('Current Configuration')
            ->assertSee('Quick Links');
    }

    public function test_settings_overview_livewire_renders_configuration_sections(): void
    {
        $user = User::factory()->superAdmin()->create();

        Livewire::actingAs($user)
            ->test(Overview::class)
            ->assertSee('Store Setting')
            ->assertSee('AI Settings')
            ->assertSee('Printing');
    }

    public function test_settings_system_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get('/admin/settings/system')
            ->assertOk()
            ->assertSee('System')
            ->assertSee('Laravel Version')
            ->assertSee('Last Backup');
    }

    public function test_settings_system_livewire_renders_runtime_details(): void
    {
        $user = User::factory()->superAdmin()->create();

        Livewire::actingAs($user)
            ->test(System::class)
            ->assertSee('Filament Version')
            ->assertSee('Queue Status')
            ->assertDontSee('Operational Notes');
    }
}
