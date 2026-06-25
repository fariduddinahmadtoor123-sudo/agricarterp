<?php

namespace Tests\Unit;

use App\Services\PurchasingInventory\PurchaseQuotationLineBuilder;
use Tests\TestCase;

class PurchaseQuotationLineBuilderTest extends TestCase
{
    public function test_line_total_multiplies_qty_and_price(): void
    {
        $row = [
            'required_qty' => '10',
            'unit_price' => '25.5',
        ];

        $this->assertSame(255.0, PurchaseQuotationLineBuilder::lineTotal($row));
    }

    public function test_grand_total_sums_all_lines(): void
    {
        $rows = [
            ['required_qty' => '2', 'unit_price' => '100'],
            ['required_qty' => '3', 'unit_price' => '50'],
        ];

        $this->assertSame(350.0, PurchaseQuotationLineBuilder::grandTotal($rows));
    }
}
