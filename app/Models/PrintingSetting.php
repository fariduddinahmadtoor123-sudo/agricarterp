<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintingSetting extends Model
{
    protected $fillable = [
        'default_document_paper',
        'default_purchase_invoice_paper',
        'price_tag_label_preset',
        'price_tag_width_mm',
        'price_tag_height_mm',
        'price_tag_gap_mm',
        'price_tag_sheet_paper',
        'barcode_printer_note',
        'pos_receipt_profile',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_tag_width_mm' => 'decimal:2',
            'price_tag_height_mm' => 'decimal:2',
            'price_tag_gap_mm' => 'decimal:2',
        ];
    }
}
