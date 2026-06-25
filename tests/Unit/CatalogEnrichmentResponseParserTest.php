<?php

namespace Tests\Unit;

use App\Services\Ai\CatalogEnrichmentResponseParser;
use PHPUnit\Framework\TestCase;

class CatalogEnrichmentResponseParserTest extends TestCase
{
    public function test_normalizes_urdu_name_aliases(): void
    {
        $parser = new CatalogEnrichmentResponseParser;

        $normalized = $parser->normalize([
            'urdu_name' => 'بوم فلٹر مش',
            'description_en' => 'Filter mesh',
        ]);

        $this->assertSame('بوم فلٹر مش', $normalized['name_ur']);
    }
}
