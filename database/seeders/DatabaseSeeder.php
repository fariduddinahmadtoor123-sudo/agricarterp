<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserNumberSequence;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            StandardUnitsSeeder::class,
            StandardAttributesSeeder::class,
            StandardProductControlsSeeder::class,
            CompanySettingSeeder::class,
            PurchasePricingSettingSeeder::class,
            RolePermissionSeeder::class,
            PrintingSettingSeeder::class,
        ]);

        User::factory()->superAdmin()->create([
            'name' => 'Admin',
            'email' => 'admin@agricarterp.com',
            'user_number' => 'USR-000001',
        ]);

        UserNumberSequence::query()->updateOrCreate(
            ['id' => 1],
            ['last_number' => 1],
        );
    }
}
