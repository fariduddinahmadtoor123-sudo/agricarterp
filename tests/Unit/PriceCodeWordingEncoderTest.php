<?php

namespace Tests\Unit;

use App\Services\PurchasingInventory\PriceCodeWordingEncoder;
use Tests\TestCase;

class PriceCodeWordingEncoderTest extends TestCase
{
    public function test_encodes_each_digit_using_configured_wording(): void
    {
        $encoder = app(PriceCodeWordingEncoder::class);

        $encoded = $encoder->encodeSalePrice('1500', [
            '0' => 'S',
            '1' => 'T',
            '2' => 'U',
            '3' => 'V',
            '4' => 'W',
            '5' => 'X',
            '6' => 'Y',
            '7' => 'Z',
            '8' => 'A',
            '9' => 'B',
        ]);

        $this->assertSame('TXSS', $encoded);
    }

    public function test_ignores_decimal_portion_when_encoding(): void
    {
        $encoder = app(PriceCodeWordingEncoder::class);

        $encoded = $encoder->encodeSalePrice('99.50', [
            '0' => 'S',
            '9' => 'B',
            '5' => 'X',
        ]);

        $this->assertSame('BBX', $encoded);
    }
}
