<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\UniversalPrinting;
use App\Models\PrintingSetting;
use App\Models\User;
use App\Services\Settings\PrintingSettingPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UniversalPrintingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_universal_printing_page_loads_for_super_admin(): void
    {
        $user = User::factory()->superAdmin()->create(['user_number' => 'USR-000001']);

        $this->actingAs($user)
            ->get('/admin/settings/universal-printing')
            ->assertOk()
            ->assertSee('Printing');
    }

    public function test_can_create_printing_settings(): void
    {
        $user = User::factory()->superAdmin()->create(['user_number' => 'USR-000001']);
        $this->actingAs($user);

        app(PrintingSettingPersistenceService::class)->create([
            'default_document_paper' => 'a4',
            'default_purchase_invoice_paper' => 'legal',
            'price_tag_label_preset' => '50x30',
            'price_tag_width_mm' => 50,
            'price_tag_height_mm' => 30,
            'price_tag_gap_mm' => 3,
            'price_tag_sheet_paper' => 'a4',
            'barcode_printer_note' => 'Zebra 50x30 loaded',
            'pos_receipt_profile' => '80mm',
        ]);

        $this->assertDatabaseHas('printing_settings', [
            'default_purchase_invoice_paper' => 'legal',
            'price_tag_label_preset' => '50x30',
            'pos_receipt_profile' => '80mm',
        ]);
    }

    public function test_add_button_hidden_when_printing_settings_exist(): void
    {
        $user = User::factory()->superAdmin()->create(['user_number' => 'USR-000001']);

        PrintingSetting::query()->create([
            'default_document_paper' => 'a4',
            'default_purchase_invoice_paper' => 'a4',
            'price_tag_label_preset' => '38x25',
            'price_tag_width_mm' => 38,
            'price_tag_height_mm' => 25,
            'price_tag_gap_mm' => 3,
            'price_tag_sheet_paper' => 'a4',
            'pos_receipt_profile' => '80mm',
        ]);

        Livewire::actingAs($user)
            ->test(UniversalPrinting::class)
            ->assertDontSee('Add Printing Settings');
    }
}
