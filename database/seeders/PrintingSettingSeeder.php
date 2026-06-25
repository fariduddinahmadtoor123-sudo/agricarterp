<?php

namespace Database\Seeders;

use App\Models\PrintingSetting;
use Illuminate\Database\Seeder;

class PrintingSettingSeeder extends Seeder
{
    public function run(): void
    {
        if (PrintingSetting::query()->exists()) {
            return;
        }

        $defaults = config('printing.defaults', []);

        PrintingSetting::query()->create([
            'default_document_paper' => $defaults['default_document_paper'] ?? 'a4',
            'default_purchase_invoice_paper' => $defaults['default_purchase_invoice_paper'] ?? 'a4',
            'price_tag_label_preset' => $defaults['price_tag_label_preset'] ?? '38x25',
            'price_tag_width_mm' => $defaults['price_tag_width_mm'] ?? 38,
            'price_tag_height_mm' => $defaults['price_tag_height_mm'] ?? 25,
            'price_tag_gap_mm' => $defaults['price_tag_gap_mm'] ?? 3,
            'price_tag_sheet_paper' => $defaults['price_tag_sheet_paper'] ?? 'a4',
            'barcode_printer_note' => $defaults['barcode_printer_note'] ?? null,
            'pos_receipt_profile' => $defaults['pos_receipt_profile'] ?? '80mm',
        ]);
    }
}
