<?php

namespace Tests\Unit;

use App\Models\CompanySetting;
use App\Services\Settings\CompanySettingLogoStorage;
use App\Services\Settings\CompanySettingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySettingResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_profile_returns_configured_company_information(): void
    {
        CompanySetting::query()->create([
            'name_en' => 'Agricart Store',
            'name_ur' => 'اگرکارٹ',
            'address_en' => 'Main Road',
            'address_ur' => 'مین روڈ',
            'phones' => [
                ['contact_person' => 'Desk', 'phone_number' => '923001234567'],
            ],
            'whatsapp_numbers' => [],
            'emails' => ['sales@agricart.test'],
            'website_url' => 'https://agricart.test',
            'ntn' => '1234567-8',
            'strn' => 'STRN-001',
            'currency' => 'PKR',
            'decimal_places' => 0,
            'timezone' => 'Asia/Karachi',
        ]);

        $resolver = app(CompanySettingResolver::class);
        $profile = $resolver->documentProfile();

        $this->assertTrue($resolver->isConfigured());
        $this->assertSame('Agricart Store', $profile['name_en']);
        $this->assertSame('اگرکارٹ', $profile['name_ur']);
        $this->assertSame('Main Road', $profile['address_en']);
        $this->assertSame('923001234567', $profile['primary_phone']);
        $this->assertSame(['sales@agricart.test'], $profile['emails']);
        $this->assertSame('https://agricart.test', $profile['website_url']);
        $this->assertSame('1234567-8', $profile['ntn']);
        $this->assertSame('STRN-001', $profile['strn']);
        $this->assertSame('PKR', $profile['currency']);
        $this->assertSame(0, $profile['decimal_places']);
        $this->assertSame('Asia/Karachi', $profile['timezone']);
        $this->assertNull($profile['logo_url']);
    }

    public function test_document_profile_falls_back_when_no_record_exists(): void
    {
        $resolver = app(CompanySettingResolver::class);
        $profile = $resolver->documentProfile();

        $this->assertFalse($resolver->isConfigured());
        $this->assertSame((string) config('agricart.brand.name', 'Agricart ERP'), $profile['name_en']);
        $this->assertSame('PKR', $profile['currency']);
        $this->assertSame(0, $profile['decimal_places']);
        $this->assertNull($profile['ntn']);
        $this->assertNull($profile['website_url']);
    }

    public function test_logo_url_uses_logo_storage_service(): void
    {
        $storage = $this->createMock(CompanySettingLogoStorage::class);
        $storage->expects($this->once())
            ->method('url')
            ->with('company-settings/logo.png')
            ->willReturn('https://example.test/logo.png');

        $this->instance(CompanySettingLogoStorage::class, $storage);

        CompanySetting::query()->create([
            'logo_path' => 'company-settings/logo.png',
            'name_en' => 'Agricart Store',
            'currency' => 'PKR',
            'decimal_places' => 0,
            'timezone' => 'Asia/Karachi',
        ]);

        $this->assertSame(
            'https://example.test/logo.png',
            app(CompanySettingResolver::class)->logoUrl(),
        );
    }
}
