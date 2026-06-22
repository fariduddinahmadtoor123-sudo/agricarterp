<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Services\ProductCatalog\UnitPersistenceService;
use Illuminate\Database\Seeder;

/**
 * Standard measurement units for Agricart ERP — agriculture, hardware,
 * machinery, spare parts, and industrial supplies. Idempotent by abbreviation.
 */
class StandardUnitsSeeder extends Seeder
{
    public function run(): void
    {
        $persistence = app(UnitPersistenceService::class);

        foreach (self::standardUnits() as $unit) {
            $existing = Unit::query()
                ->whereNormalizedAbbreviation($unit['abbreviation_en'])
                ->first();

            if ($existing !== null) {
                if (! $existing->is_standard) {
                    $existing->update([
                        'is_standard' => true,
                        'unit_type' => $unit['unit_type'],
                        'sort_order' => $unit['sort_order'],
                    ]);
                }

                continue;
            }

            $persistence->create([
                'name_en' => $unit['name_en'],
                'abbreviation_en' => $unit['abbreviation_en'],
                'unit_type' => $unit['unit_type'],
                'is_standard' => true,
                'sort_order' => $unit['sort_order'],
            ]);
        }
    }

    /**
     * @return list<array{name_en: string, abbreviation_en: string, unit_type: string, sort_order: int}>
     */
    public static function standardUnits(): array
    {
        return [
            // Weight — fertilizers, seed, metal stock, fasteners by weight
            ['name_en' => 'Kilogram', 'abbreviation_en' => 'kg', 'unit_type' => Unit::TYPE_WEIGHT, 'sort_order' => 10],
            ['name_en' => 'Gram', 'abbreviation_en' => 'g', 'unit_type' => Unit::TYPE_WEIGHT, 'sort_order' => 20],
            ['name_en' => 'Milligram', 'abbreviation_en' => 'mg', 'unit_type' => Unit::TYPE_WEIGHT, 'sort_order' => 30],
            ['name_en' => 'Metric Ton', 'abbreviation_en' => 't', 'unit_type' => Unit::TYPE_WEIGHT, 'sort_order' => 40],
            ['name_en' => 'Quintal', 'abbreviation_en' => 'q', 'unit_type' => Unit::TYPE_WEIGHT, 'sort_order' => 50],

            // Volume — fuel, lubricants, pesticides, liquids
            ['name_en' => 'Liter', 'abbreviation_en' => 'L', 'unit_type' => Unit::TYPE_VOLUME, 'sort_order' => 110],
            ['name_en' => 'Milliliter', 'abbreviation_en' => 'mL', 'unit_type' => Unit::TYPE_VOLUME, 'sort_order' => 120],
            ['name_en' => 'Gallon', 'abbreviation_en' => 'gal', 'unit_type' => Unit::TYPE_VOLUME, 'sort_order' => 130],
            ['name_en' => 'Bushel', 'abbreviation_en' => 'bu', 'unit_type' => Unit::TYPE_VOLUME, 'sort_order' => 140],

            // Length — pipe, cable, belt, bar, sheet goods
            ['name_en' => 'Meter', 'abbreviation_en' => 'm', 'unit_type' => Unit::TYPE_LENGTH, 'sort_order' => 210],
            ['name_en' => 'Centimeter', 'abbreviation_en' => 'cm', 'unit_type' => Unit::TYPE_LENGTH, 'sort_order' => 220],
            ['name_en' => 'Millimeter', 'abbreviation_en' => 'mm', 'unit_type' => Unit::TYPE_LENGTH, 'sort_order' => 230],
            ['name_en' => 'Foot', 'abbreviation_en' => 'ft', 'unit_type' => Unit::TYPE_LENGTH, 'sort_order' => 240],
            ['name_en' => 'Inch', 'abbreviation_en' => 'in', 'unit_type' => Unit::TYPE_LENGTH, 'sort_order' => 250],

            // Area — land, flooring, sheeting, agricultural plots
            ['name_en' => 'Square Meter', 'abbreviation_en' => 'm²', 'unit_type' => Unit::TYPE_AREA, 'sort_order' => 310],
            ['name_en' => 'Square Foot', 'abbreviation_en' => 'ft²', 'unit_type' => Unit::TYPE_AREA, 'sort_order' => 320],
            ['name_en' => 'Square Yard', 'abbreviation_en' => 'sq yd', 'unit_type' => Unit::TYPE_AREA, 'sort_order' => 330],
            ['name_en' => 'Acre', 'abbreviation_en' => 'ac', 'unit_type' => Unit::TYPE_AREA, 'sort_order' => 340],
            ['name_en' => 'Hectare', 'abbreviation_en' => 'ha', 'unit_type' => Unit::TYPE_AREA, 'sort_order' => 350],
            ['name_en' => 'Kanal', 'abbreviation_en' => 'kanal', 'unit_type' => Unit::TYPE_AREA, 'sort_order' => 360],
            ['name_en' => 'Marla', 'abbreviation_en' => 'marla', 'unit_type' => Unit::TYPE_AREA, 'sort_order' => 370],

            // Count — individual parts, fasteners, components
            ['name_en' => 'Piece', 'abbreviation_en' => 'pcs', 'unit_type' => Unit::TYPE_COUNT, 'sort_order' => 410],
            ['name_en' => 'Each', 'abbreviation_en' => 'ea', 'unit_type' => Unit::TYPE_COUNT, 'sort_order' => 420],
            ['name_en' => 'Unit', 'abbreviation_en' => 'unit', 'unit_type' => Unit::TYPE_COUNT, 'sort_order' => 430],
            ['name_en' => 'Set', 'abbreviation_en' => 'set', 'unit_type' => Unit::TYPE_COUNT, 'sort_order' => 440],
            ['name_en' => 'Pair', 'abbreviation_en' => 'pr', 'unit_type' => Unit::TYPE_COUNT, 'sort_order' => 450],
            ['name_en' => 'Dozen', 'abbreviation_en' => 'dz', 'unit_type' => Unit::TYPE_COUNT, 'sort_order' => 460],
            ['name_en' => 'Kit', 'abbreviation_en' => 'kit', 'unit_type' => Unit::TYPE_COUNT, 'sort_order' => 470],
            ['name_en' => 'Lot', 'abbreviation_en' => 'lot', 'unit_type' => Unit::TYPE_COUNT, 'sort_order' => 480],

            // Packaging — bags, drums, rolls, industrial outer packs
            ['name_en' => 'Pack', 'abbreviation_en' => 'pk', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 510],
            ['name_en' => 'Box', 'abbreviation_en' => 'box', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 520],
            ['name_en' => 'Bag', 'abbreviation_en' => 'bag', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 530],
            ['name_en' => 'Sack', 'abbreviation_en' => 'sack', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 540],
            ['name_en' => 'Bale', 'abbreviation_en' => 'bale', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 550],
            ['name_en' => 'Bundle', 'abbreviation_en' => 'bdl', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 560],
            ['name_en' => 'Roll', 'abbreviation_en' => 'roll', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 570],
            ['name_en' => 'Coil', 'abbreviation_en' => 'coil', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 580],
            ['name_en' => 'Spool', 'abbreviation_en' => 'spool', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 590],
            ['name_en' => 'Reel', 'abbreviation_en' => 'reel', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 600],
            ['name_en' => 'Bottle', 'abbreviation_en' => 'bt', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 610],
            ['name_en' => 'Can', 'abbreviation_en' => 'can', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 620],
            ['name_en' => 'Drum', 'abbreviation_en' => 'drum', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 630],
            ['name_en' => 'Bucket', 'abbreviation_en' => 'bkt', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 640],
            ['name_en' => 'Tube', 'abbreviation_en' => 'tube', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 650],
            ['name_en' => 'Cartridge', 'abbreviation_en' => 'cart', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 660],
            ['name_en' => 'Carton', 'abbreviation_en' => 'ctn', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 670],
            ['name_en' => 'Crate', 'abbreviation_en' => 'crt', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 680],
            ['name_en' => 'Pallet', 'abbreviation_en' => 'plt', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 690],
            ['name_en' => 'Sheet', 'abbreviation_en' => 'sht', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 700],
            ['name_en' => 'Case', 'abbreviation_en' => 'cs', 'unit_type' => Unit::TYPE_PACKAGING, 'sort_order' => 710],
        ];
    }
}
