<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class CompanySettingSeeder extends Seeder
{
    public function run(): void
    {
        if (CompanySetting::query()->exists()) {
            return;
        }

        CompanySetting::query()->create([
            'name_en' => (string) config('agricart.brand.name', 'Agricart ERP'),
            'name_ur' => '',
            'currency' => 'PKR',
            'decimal_places' => 0,
            'timezone' => 'Asia/Karachi',
            'phones' => [],
            'whatsapp_numbers' => [],
            'emails' => [],
        ]);
    }
}
