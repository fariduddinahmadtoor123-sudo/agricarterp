<?php

namespace Tests\Unit;

use App\Services\PurchasingInventory\PriceTagBarcodeGenerator;
use Tests\TestCase;

class PriceTagBarcodeGeneratorTest extends TestCase
{
    public function test_generates_code128_svg_for_alphanumeric_sku(): void
    {
        $svg = app(PriceTagBarcodeGenerator::class)->svg('PRD-000005');

        $this->assertNotNull($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('<rect', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    public function test_generates_ean13_svg_for_valid_thirteen_digit_values(): void
    {
        $svg = app(PriceTagBarcodeGenerator::class)->svg('8901234567890');

        $this->assertNotNull($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('<rect', $svg);
    }

    public function test_returns_null_for_blank_or_invalid_values(): void
    {
        $generator = app(PriceTagBarcodeGenerator::class);

        $this->assertNull($generator->svg(''));
        $this->assertNull($generator->svg(null));
        $this->assertNull($generator->svg("PRD\x00BAD"));
        $this->assertNull($generator->svg('1234567890123'));
    }
}
