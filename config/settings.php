<?php

return [
    'logo_disk' => env('COMPANY_SETTING_LOGO_DISK', 'local'),

    'currencies' => [
        'PKR' => 'PKR — Pakistani Rupee',
    ],

    'decimal_places' => [
        0 => '0',
        1 => '1',
        2 => '2',
        3 => '3',
        4 => '4',
    ],

    'timezones' => [
        'Asia/Karachi' => 'Asia/Karachi (PKT)',
    ],

    'purchase_pricing' => [
        'update_product_prices_from_purchases' => false,
        'wholesale_markup_pct' => '10',
        'super_wholesale_markup_pct' => '8',
        'distributor_markup_pct' => '12',
        'default_price_code_wording' => [
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
        ],
    ],
];
