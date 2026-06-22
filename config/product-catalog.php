<?php

return [
    'category_image_disk' => env('PRODUCT_CATALOG_CATEGORY_IMAGE_DISK', 'local'),

    'brand_logo_disk' => env('PRODUCT_CATALOG_BRAND_LOGO_DISK', 'local'),

    'category_statuses' => [
        'active' => 'Active',
        'archived' => 'Archived',
    ],

    'brand_statuses' => [
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

    'brand_ai_statuses' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'complete' => 'Complete',
        'review' => 'Review',
        'failed' => 'Failed',
    ],

    'unit_statuses' => [
        'active' => 'Active',
        'archived' => 'Archived',
    ],

    'unit_types' => [
        'weight' => 'Weight',
        'volume' => 'Volume',
        'length' => 'Length',
        'area' => 'Area',
        'count' => 'Count',
        'packaging' => 'Packaging',
    ],

    'unit_ai_statuses' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'complete' => 'Complete',
        'review' => 'Review',
        'failed' => 'Failed',
    ],

    'attribute_statuses' => [
        'active' => 'Active',
        'archived' => 'Archived',
    ],

    'control_statuses' => [
        'active' => 'Active',
        'archived' => 'Archived',
    ],

    'control_group_statuses' => [
        'active' => 'Active',
        'archived' => 'Archived',
    ],

    'control_types' => [
        'warranty' => 'Warranty',
        'guarantee' => 'Guarantee',
        'return_policy' => 'Return Policy',
        'replacement_policy' => 'Replacement Policy',
        'handling_alert' => 'Handling Alert',
        'usage_note' => 'Usage Note',
        'warning' => 'Warning',
    ],

    'product_image_disk' => env('PRODUCT_CATALOG_PRODUCT_IMAGE_DISK', 'local'),

    'product_statuses' => [
        'active' => 'Active',
        'archived' => 'Archived',
    ],

    'product_ai_statuses' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'complete' => 'Complete',
        'review' => 'Review',
        'failed' => 'Failed',
    ],
];
