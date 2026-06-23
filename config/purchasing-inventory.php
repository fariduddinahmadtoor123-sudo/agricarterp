<?php

return [
    'sheet_statuses' => [
        'draft' => 'Draft',
        'saved' => 'Saved',
    ],

    /** Minimum visible product rows on the worksheet grid (empty placeholders). */
    'worksheet_min_visual_rows' => 10,

    /** Demo store options until inventory stores are configured. */
    'demo_stores' => [
        'main' => 'Main Warehouse (Demo)',
        'saddar' => 'Saddar Branch (Demo)',
        'model' => 'Model Town Outlet (Demo)',
    ],

    'demo_default_store' => 'main',

    'purchase_invoice_payment_statuses' => [
        'unpaid' => 'Unpaid',
        'partial' => 'Partial Paid',
        'paid' => 'Paid',
    ],

    'purchase_goods_receipt_statuses' => [
        'pending' => 'Not Received',
        'partial' => 'Partially Received',
        'received' => 'Received',
    ],

    'purchase_dispute_statuses' => [
        'none' => 'No Dispute',
        'damaged' => 'Damaged Goods',
        'short' => 'Short Delivery',
        'ok' => 'All OK',
        'return' => 'Invoice Return',
    ],

    /** Default tier mark-up % on purchase rate (landing excluded). */
    'purchase_pricing_tiers' => [
        'wholesale' => ['label' => 'Wholesale', 'default_pct' => '10'],
        'super_wholesale' => ['label' => 'Super Wholesale', 'default_pct' => '8'],
        'distributor' => ['label' => 'Distributor', 'default_pct' => '12'],
    ],

    /** Hide store selector and use demo_default_store when true. */
    'single_store_mode' => true,

    'purchase_print_paper_sizes' => [
        'a4' => 'A4',
        'compact' => 'Compact',
    ],

    /** Purchase payment sheet — dynamic rows (printing / cash chit). */
    'payment_sheet_default_vendor_rows' => 10,
    'payment_sheet_default_source_rows' => 3,
    'payment_sheet_max_vendor_rows' => 100,
    'payment_sheet_max_source_rows' => 50,
    'payment_sheet_print_blank_vendor_rows' => 3,

    /** Re-order center (session preview until inventory module). */
    'reorder_stale_days' => 7,
    'reorder_queue_statuses' => [
        'pending' => 'Pending',
        'stale' => 'Stale',
        'disputed' => 'Disputed',
        'received' => 'Received',
    ],
    'reorder_stock_filters' => [
        'all' => 'All Needs',
        'low' => 'Low Stock',
        'out' => 'Out of Stock',
    ],
    'reorder_queue_filters' => [
        'all' => 'All Queue',
        'pending' => 'Pending',
        'stale' => 'Stale',
        'disputed' => 'Disputed',
    ],

    /** Price tag printing queue & sticker options. */
    'price_tag_scan_modes' => [
        'barcode' => 'Barcode',
        'qr' => 'QR',
        'both' => 'Both',
        'none' => 'None',
    ],
    'price_tag_default_scan_mode' => 'barcode',
    'price_tag_print_fields' => [
        'store_name' => 'Store name',
        'name_en' => 'English name',
        'name_ur' => 'Urdu name',
        'brand' => 'Brand',
        'unit' => 'Unit',
        'sku' => 'SKU under code',
        'sale_price' => 'Sale price (digits)',
        'purchase_price' => 'Purchase price',
        'landing_cost' => 'Landing cost',
        'wholesale' => 'Wholesale',
        'super_wholesale' => 'Super WS',
        'distributor' => 'Distributor',
        'tier_codes' => 'Tier codes',
        'purchase_code' => 'Purchase code',
    ],
    'price_tag_default_fields' => [
        'store_name' => true,
        'name_en' => true,
        'name_ur' => false,
        'brand' => true,
        'unit' => true,
        'sku' => true,
        'sale_price' => true,
        'purchase_price' => false,
        'landing_cost' => false,
        'wholesale' => false,
        'super_wholesale' => false,
        'distributor' => false,
        'tier_codes' => true,
        'purchase_code' => true,
    ],

    /** Physical label size for screen preview and print layout (thermal/A4 sheets). */
    'price_tag_label' => [
        'width_mm' => 38,
        'height_mm' => 25,
        'gap_mm' => 3,
    ],
];
