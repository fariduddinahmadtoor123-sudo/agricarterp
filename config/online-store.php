<?php

return [
    'pages_disk' => env('ONLINE_STORE_PAGES_DISK', 'local'),

    'footer_logo_disk' => env('ONLINE_STORE_FOOTER_LOGO_DISK', 'local'),

    'footer_logo_directory' => 'online-store/footer',

    'social_platforms' => [
        'facebook' => 'Facebook',
        'youtube' => 'YouTube',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'whatsapp' => 'WhatsApp',
        'linkedin' => 'LinkedIn',
        'x' => 'X (Twitter)',
    ],

    'homepage_categories_per_row_options' => [
        3 => '3 categories per row',
        4 => '4 categories per row',
        5 => '5 categories per row',
        6 => '6 categories per row',
    ],

    'default_homepage_categories_per_row' => 5,

    'tablet_categories_per_row' => 2,

    'mobile_categories_per_row' => 1,
];
