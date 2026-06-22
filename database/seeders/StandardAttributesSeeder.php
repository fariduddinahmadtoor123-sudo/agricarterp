<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Services\ProductCatalog\AttributePersistenceService;
use Illuminate\Database\Seeder;

/**
 * Standard product-specification attribute names for Agricart ERP.
 * Names only — product values are entered later in the Products module.
 */
class StandardAttributesSeeder extends Seeder
{
    public function run(): void
    {
        $persistence = app(AttributePersistenceService::class);

        foreach (self::standardAttributeNames() as $name) {
            if (Attribute::query()->whereNormalizedName($name)->exists()) {
                continue;
            }

            $persistence->create([
                'name' => $name,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public static function standardAttributeNames(): array
    {
        return [
            'Color',
            'Weight',
            'Length',
            'Width',
            'Height',
            'Depth',
            'Diameter',
            'Inner Diameter',
            'Outer Diameter',
            'Thickness',
            'Bore Size',
            'Shaft Diameter',
            'Shank Size',
            'Arbor Size',
            'Material',
            'Finish',
            'Grade',
            'Hardness',
            'Voltage',
            'Current',
            'Power',
            'Phase',
            'Frequency',
            'RPM',
            'Horsepower',
            'Kilowatt Rating',
            'Insulation Class',
            'Wire Gauge',
            'Pressure',
            'Working Pressure',
            'Flow Rate',
            'Capacity',
            'Volume',
            'Viscosity Grade',
            'Oil Type',
            'Fuel Type',
            'Pump Head',
            'Hose Size',
            'Pipe Size',
            'Nominal Bore',
            'Thread Size',
            'Thread Pitch',
            'Connection Type',
            'Flange Size',
            'Port Size',
            'Suction Size',
            'Delivery Size',
            'Bearing Type',
            'Bearing Series',
            'Seal Type',
            'Grease Type',
            'Keyway Size',
            'Displacement',
            'Cylinder Count',
            'Stroke',
            'Gear Ratio',
            'Speed Ratio',
            'PTO Speed',
            'PTO Shaft Size',
            'Working Width',
            'Cutting Width',
            'Coverage Width',
            'Row Spacing',
            'Teeth Count',
            'Blade Count',
            'Tines Count',
            'Tooth Pitch',
            'Cutting Edge Type',
            'Spray Pattern',
            'Nozzle Type',
            'Belt Section',
            'Belt Length',
            'Chain Pitch',
            'Chain Links',
            'Pulley Groove',
            'Tyre Size',
            'Rim Size',
            'Ply Rating',
        ];
    }
}
