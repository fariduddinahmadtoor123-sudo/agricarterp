<?php

namespace Tests\Unit;

use App\Services\Contacts\MobileNumberNormalizer;
use PHPUnit\Framework\TestCase;

class MobileNumberNormalizerTest extends TestCase
{
    public function test_normalizes_pakistan_local_format_to_international_digits(): void
    {
        $normalizer = new MobileNumberNormalizer;

        $this->assertSame('923001234567', $normalizer->normalize('0300-1234567'));
    }

    public function test_strips_non_digit_characters(): void
    {
        $normalizer = new MobileNumberNormalizer;

        $this->assertSame('923331234567', $normalizer->normalize('+92 333 1234567'));
    }

    public function test_returns_null_for_blank_values(): void
    {
        $normalizer = new MobileNumberNormalizer;

        $this->assertNull($normalizer->normalize(null));
        $this->assertNull($normalizer->normalize(''));
    }
}
