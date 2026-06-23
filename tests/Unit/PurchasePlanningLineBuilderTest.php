<?php

namespace Tests\Unit;

use App\Services\PurchasingInventory\PurchasePlanningLineBuilder;
use PHPUnit\Framework\TestCase;

class PurchasePlanningLineBuilderTest extends TestCase
{
    public function test_urdu_mode_does_not_fallback_to_english(): void
    {
        $row = [
            'name_en' => 'English Product Name',
            'name_ur' => '',
        ];

        $this->assertSame('', PurchasePlanningLineBuilder::displayName($row, 'ur'));
    }

    public function test_english_mode_does_not_fallback_to_urdu(): void
    {
        $row = [
            'name_en' => '',
            'name_ur' => 'اردو نام',
        ];

        $this->assertSame('', PurchasePlanningLineBuilder::displayName($row, 'en'));
    }

    public function test_both_mode_shows_available_names_only(): void
    {
        $row = [
            'name_en' => 'Pump Set',
            'name_ur' => '',
        ];

        $this->assertSame('Pump Set', PurchasePlanningLineBuilder::displayName($row, 'both'));
    }
}
