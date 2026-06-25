<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\GeneralSettings;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get('/admin/settings/general-settings')
            ->assertOk()
            ->assertSee('Store Setting');
    }

    public function test_create_action_hidden_when_record_exists(): void
    {
        $user = User::factory()->superAdmin()->create();

        CompanySetting::query()->create([
            'name_en' => 'Existing Store',
            'currency' => 'PKR',
            'decimal_places' => 0,
            'timezone' => 'Asia/Karachi',
        ]);

        Livewire::actingAs($user)
            ->test(GeneralSettings::class)
            ->assertDontSee('Add Company / Main Store');
    }

    public function test_add_button_visible_when_no_record_exists(): void
    {
        $user = User::factory()->superAdmin()->create();

        Livewire::actingAs($user)
            ->test(GeneralSettings::class)
            ->assertSee('Add Company / Main Store');
    }

    public function test_table_shows_existing_record(): void
    {
        $user = User::factory()->superAdmin()->create();

        CompanySetting::query()->create([
            'name_en' => 'Main Store',
            'currency' => 'PKR',
            'decimal_places' => 0,
            'timezone' => 'Asia/Karachi',
        ]);

        Livewire::actingAs($user)
            ->test(GeneralSettings::class)
            ->assertSee('Main Store');
    }
}
