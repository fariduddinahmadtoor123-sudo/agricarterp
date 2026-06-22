<?php

namespace Database\Seeders;

use App\Models\ProductControl;
use App\Services\ProductCatalog\ProductControlPersistenceService;
use Illuminate\Database\Seeder;

/**
 * Standard reusable product controls for Agricart ERP — e-commerce, hardware,
 * agricultural machinery, spare parts, pumps, motors, bearings, and industrial equipment.
 * Idempotent by normalized control name.
 */
class StandardProductControlsSeeder extends Seeder
{
    public function run(): void
    {
        $persistence = app(ProductControlPersistenceService::class);

        foreach (self::standardControls() as $control) {
            if (ProductControl::query()->whereNormalizedName($control['name'])->exists()) {
                continue;
            }

            $persistence->create([
                'name' => $control['name'],
                'control_type' => $control['control_type'],
            ]);
        }
    }

    /**
     * @return list<array{name: string, control_type: string}>
     */
    public static function standardControls(): array
    {
        return array_merge(
            self::warrantyControls(),
            self::guaranteeControls(),
            self::returnPolicyControls(),
            self::replacementPolicyControls(),
            self::handlingAlertControls(),
            self::usageNoteControls(),
            self::warningControls(),
        );
    }

    /**
     * @return list<array{name: string, control_type: string}>
     */
    public static function warrantyControls(): array
    {
        return self::controls(ProductControl::TYPE_WARRANTY, [
            'Motor winding covered',
            'Manufacturing defects covered',
            'Warranty valid with invoice',
            'Warranty starts from purchase date',
            'Bearings covered against manufacturing defect',
            'Pump housing crack covered if factory defect',
            'Engine block casting defect covered',
            'Electrical fault covered within warranty period',
            'Rotavator blade breakage covered if material defect',
            'Gearbox internal defect covered',
            'Spare part warranty per manufacturer terms',
            'Warranty void if serial number removed',
            'Warranty covers parts only labor excluded',
            'Warranty transferable with original invoice',
            'Industrial motor warranty per manufacturer standard',
            'Shaft breakage covered if manufacturing fault',
            'Seal leak covered if factory assembly defect',
            'Warranty period as stated on invoice',
            'Warranty claim requires original invoice',
        ]);
    }

    /**
     * @return list<array{name: string, control_type: string}>
     */
    public static function guaranteeControls(): array
    {
        return self::controls(ProductControl::TYPE_GUARANTEE, [
            'Genuine product guarantee',
            'Copper winding guaranteed',
            'Original material guaranteed',
            'OEM quality guaranteed',
            'Authentic brand guarantee',
            'Factory specification guaranteed',
            'Genuine spare part guarantee',
            'No duplicate or refurbished unit guarantee',
            'Cast iron body genuine guarantee',
            'Imported quality as specified guarantee',
            'Seal kit genuine components guaranteed',
            'Electrical rating as labeled guaranteed',
            'Thread size as per standard guaranteed',
            'Corrosion-resistant coating guaranteed',
        ]);
    }

    /**
     * @return list<array{name: string, control_type: string}>
     */
    public static function returnPolicyControls(): array
    {
        return self::controls(ProductControl::TYPE_RETURN_POLICY, [
            'Unused return allowed',
            'Return within 7 days',
            'Original packaging required',
            'Return with complete accessories',
            'Return with original invoice',
            'No return on electrical goods once installed',
            'No return on cut-to-size items',
            'No return on opened lubricants',
            'Return accepted if wrong item supplied',
            'Return freight buyer responsibility',
            'Return inspection required before refund',
            'Refund after deduction of handling charges',
            'No return on special order items',
            'No return on sale or clearance items',
            'Return allowed if sealed pack unopened',
            'Return not accepted after installation',
            'Belt and hose returns if unopened only',
            'No return on consumable parts once used',
            'Check product before accepting delivery',
        ]);
    }

    /**
     * @return list<array{name: string, control_type: string}>
     */
    public static function replacementPolicyControls(): array
    {
        return self::controls(ProductControl::TYPE_REPLACEMENT_POLICY, [
            'Defective unit replaced within 7 days',
            'Wrong item eligible for replacement',
            'Damaged in transit replaced on report within 24 hours',
            'Manufacturing defect replaced same model',
            'Equivalent replacement if exact model unavailable',
            'Replacement subject to stock availability',
            'Motor replaced if winding failure under warranty',
            'Pump replaced if housing crack is factory defect',
            'Bearing replaced if cage failure under warranty',
            'Replacement only with original packaging and invoice',
            'Blade replaced if breakage is material defect',
            'Seal kit replaced if factory defect confirmed',
            'No replacement after customer modification',
            'Electrical item replaced once if DOA confirmed',
            'Replacement unit carries balance of original warranty',
            'Wrong specification supplied eligible for exchange',
            'Coupling replaced if factory machining defect',
            'Rotavator part replaced if factory defect confirmed',
        ]);
    }

    /**
     * @return list<array{name: string, control_type: string}>
     */
    public static function handlingAlertControls(): array
    {
        return self::controls(ProductControl::TYPE_HANDLING_ALERT, [
            'Handle with care',
            'Heavy item',
            'Fragile item',
            'Keep dry',
            'Store upright only',
            'Do not stack more than 3 units',
            'Lift with equipment do not drag',
            'Protect from rain during transport',
            'Oil-filled unit transport upright only',
            'Sharp edges wear gloves when handling',
            'Magnetic bearing keep away from debris',
            'Coil winding do not impact or drop',
            'Store in shaded dry area',
            'Temperature sensitive store below 40°C',
            'Forklift slots only for lifting',
            'Do not place heavy weight on shaft',
            'Hose coil avoid kinking',
            'Glass sight gauge fragile handle carefully',
        ]);
    }

    /**
     * @return list<array{name: string, control_type: string}>
     */
    public static function usageNoteControls(): array
    {
        return self::controls(ProductControl::TYPE_USAGE_NOTE, [
            'Grease bearings before use',
            'Read manual before operation',
            'Check oil level before startup',
            'Prime pump before first run',
            'Warm up engine before full load',
            'Match pulley size to recommended RPM',
            'Use specified grade engine oil only',
            'Tighten mounting bolts to torque spec',
            'Align shaft coupling before operation',
            'Clean air filter before each season',
            'Check belt tension before use',
            'Flush system before installing new pump',
            'Break in new bearing with light load first hour',
            'Use correct voltage and phase only',
            'Lubricate PTO splines before attachment',
            'Inspect blades before each rotavator use',
            'Run motor uncoupled briefly to check rotation',
            'Replace consumable seals at recommended interval',
            'Installation required by qualified technician',
        ]);
    }

    /**
     * @return list<array{name: string, control_type: string}>
     */
    public static function warningControls(): array
    {
        return self::controls(ProductControl::TYPE_WARNING, [
            'Burnt motor not covered',
            'Do not operate without guard',
            'Do not run dry',
            'Warranty void if modified',
            'Do not exceed rated RPM',
            'High voltage shock hazard qualified personnel only',
            'Rotating parts keep hands clear',
            'Do not use with damaged cord or plug',
            'Overloading voids warranty',
            'Incorrect wiring burns motor immediately',
            'Dry running destroys mechanical seal',
            'Improper installation voids warranty',
            'Using non-spec oil causes bearing failure',
            'Reverse rotation damages pump impeller',
            'Exceeding pressure rating may burst housing',
            'Do not weld on bearing housing',
            'Mixing incompatible hydraulic fluid damages system',
            'Operating without earthing risks electric shock',
            'Product specifications may vary by manufacturer',
        ]);
    }

    /**
     * @param  list<string>  $names
     * @return list<array{name: string, control_type: string}>
     */
    protected static function controls(string $controlType, array $names): array
    {
        return array_map(
            fn (string $name): array => [
                'name' => $name,
                'control_type' => $controlType,
            ],
            $names,
        );
    }
}
