<?php

return [
    'document_paper_sizes' => [
        'a4' => [
            'label' => 'A4',
            'width_mm' => 210,
            'height_mm' => 297,
            'css_page' => 'A4 portrait',
        ],
        'a5' => [
            'label' => 'A5',
            'width_mm' => 148,
            'height_mm' => 210,
            'css_page' => 'A5 portrait',
        ],
        'legal' => [
            'label' => 'Legal',
            'width_mm' => 216,
            'height_mm' => 356,
            'css_page' => 'legal portrait',
        ],
        'letter' => [
            'label' => 'US Letter',
            'width_mm' => 216,
            'height_mm' => 279,
            'css_page' => 'letter portrait',
        ],
    ],

    'label_sheet_papers' => [
        'a4' => 'A4 sheet',
        'letter' => 'US Letter sheet',
    ],

    'label_presets' => [
        '38x25' => [
            'label' => '38 × 25 mm',
            'width_mm' => 38,
            'height_mm' => 25,
            'gap_mm' => 3,
        ],
        '40x30' => [
            'label' => '40 × 30 mm',
            'width_mm' => 40,
            'height_mm' => 30,
            'gap_mm' => 3,
        ],
        '50x25' => [
            'label' => '50 × 25 mm',
            'width_mm' => 50,
            'height_mm' => 25,
            'gap_mm' => 3,
        ],
        '50x30' => [
            'label' => '50 × 30 mm',
            'width_mm' => 50,
            'height_mm' => 30,
            'gap_mm' => 3,
        ],
        'custom' => [
            'label' => 'Custom size',
            'width_mm' => 38,
            'height_mm' => 25,
            'gap_mm' => 3,
        ],
    ],

    'thermal_receipt_profiles' => [
        '80mm' => [
            'label' => '3 inch / 80 mm roll',
            'width_mm' => 80,
            'css_page' => '80mm auto',
        ],
        '58mm' => [
            'label' => '2 inch / 58 mm roll',
            'width_mm' => 58,
            'css_page' => '58mm auto',
        ],
    ],

    'defaults' => [
        'default_document_paper' => 'a4',
        'default_purchase_invoice_paper' => 'a4',
        'price_tag_label_preset' => '38x25',
        'price_tag_width_mm' => '38',
        'price_tag_height_mm' => '25',
        'price_tag_gap_mm' => '3',
        'price_tag_sheet_paper' => 'a4',
        'barcode_printer_note' => null,
        'pos_receipt_profile' => '80mm',
    ],
];
