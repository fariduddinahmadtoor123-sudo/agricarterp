<?php

return [
    'category_image_disk' => env('PRODUCT_CATALOG_CATEGORY_IMAGE_DISK', 'local'),

    'category_statuses' => [
        'active' => 'Active',
        'archived' => 'Archived',
    ],

    'category_ai_statuses' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'complete' => 'Complete',
        'review' => 'Review',
        'failed' => 'Failed',
    ],
];
