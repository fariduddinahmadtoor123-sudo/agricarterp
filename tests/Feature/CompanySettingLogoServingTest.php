<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\User;
use App\Services\Settings\CompanySettingLogoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanySettingLogoServingTest extends TestCase
{
    use RefreshDatabase;

    public function test_serves_company_logo_for_authenticated_user(): void
    {
        Storage::fake('public');

        $path = 'company-settings/test-logo.webp';
        Storage::disk('public')->put($path, 'fake-image');

        $setting = CompanySetting::query()->create([
            'name_en' => 'Test Store',
            'logo_path' => $path,
            'currency' => 'PKR',
            'decimal_places' => 0,
            'timezone' => 'Asia/Karachi',
        ]);

        $user = User::factory()->superAdmin()->create();
        $url = app(CompanySettingLogoStorage::class)->url($setting->logo_path);

        $this->assertNotNull($url);

        $response = $this->actingAs($user)->get($url);

        $response->assertOk();
    }
}
