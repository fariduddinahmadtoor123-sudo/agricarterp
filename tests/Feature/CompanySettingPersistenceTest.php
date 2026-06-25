<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Settings\CompanySettingPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompanySettingPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_company_setting_record(): void
    {
        $setting = app(CompanySettingPersistenceService::class)->create($this->payload());

        $this->assertSame('Agricart Traders', $setting->name_en);
        $this->assertSame('PKR', $setting->currency);
        $this->assertSame(0, $setting->decimal_places);
        $this->assertSame('Asia/Karachi', $setting->timezone);
        $this->assertSame([
            [
                'contact_person' => 'Main Office',
                'phone_number' => '923001234567',
            ],
        ], $setting->phones);
    }

    public function test_rejects_second_company_setting_record(): void
    {
        app(CompanySettingPersistenceService::class)->create($this->payload());

        $this->expectException(ValidationException::class);

        app(CompanySettingPersistenceService::class)->create($this->payload([
            'name_en' => 'Another Store',
        ]));
    }

    public function test_updates_existing_company_setting(): void
    {
        $setting = app(CompanySettingPersistenceService::class)->create($this->payload());

        $updated = app(CompanySettingPersistenceService::class)->update($setting, $this->payload([
            'name_en' => 'Updated Name',
            'emails' => [
                ['email' => 'info@example.com'],
            ],
        ]));

        $this->assertSame('Updated Name', $updated->name_en);
        $this->assertSame(['info@example.com'], $updated->emails);
    }

    public function test_migrates_legacy_phone_strings_on_update(): void
    {
        $setting = app(CompanySettingPersistenceService::class)->create($this->payload([
            'phones' => ['03001234567'],
        ]));

        $this->assertSame([
            [
                'contact_person' => null,
                'phone_number' => '923001234567',
            ],
        ], $setting->phones);

        $updated = app(CompanySettingPersistenceService::class)->update($setting, $this->payload([
            'phones' => [
                [
                    'contact_person' => 'Sales Desk',
                    'phone_number' => '03001234567',
                ],
            ],
            'whatsapp_numbers' => [
                [
                    'contact_person' => 'Support',
                    'whatsapp_number' => '03007654321',
                ],
            ],
        ]));

        $this->assertSame('Sales Desk', $updated->phones[0]['contact_person']);
        $this->assertSame('923001234567', $updated->phones[0]['phone_number']);
        $this->assertSame('Support', $updated->whatsapp_numbers[0]['contact_person']);
        $this->assertSame('923007654321', $updated->whatsapp_numbers[0]['whatsapp_number']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name_en' => 'Agricart Traders',
            'name_ur' => 'اگرکارٹ',
            'address_en' => 'Main Bazaar',
            'address_ur' => null,
            'phones' => [
                [
                    'contact_person' => 'Main Office',
                    'phone_number' => '0300-1234567',
                ],
            ],
            'whatsapp_numbers' => [],
            'emails' => [],
            'website_url' => null,
            'ntn' => null,
            'strn' => null,
            'currency' => 'PKR',
            'decimal_places' => 0,
            'timezone' => 'Asia/Karachi',
        ], $overrides);
    }
}
