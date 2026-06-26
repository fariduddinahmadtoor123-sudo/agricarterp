<?php

return [
    'payment_methods' => [
        'cash' => 'Cash',
        'card' => 'Card',
        'bank_transfer' => 'Bank Transfer',
        'mobile_wallet' => 'Mobile Wallet',
        'credit' => 'Customer Credit',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'held' => 'On Hold',
        'completed' => 'Completed',
        'void' => 'Void',
    ],

    'print_paper_sizes' => [
        '80mm' => 'Thermal 80mm',
        '58mm' => 'Thermal 58mm',
        'a4' => 'A4',
    ],

    'default_payment_method' => 'cash',

    'walk_in_customer_label' => 'Walk-in Customer',

    'worksheet_min_visual_rows' => 0,

    'product_search_min_chars' => 2,
    'product_search_limit' => 12,
    'customer_search_min_chars' => 2,
    'customer_search_limit' => 15,

    'quotation_statuses' => [
        'draft' => 'Draft',
        'held' => 'On Hold',
        'finalized' => 'Finalized',
        'void' => 'Void',
    ],

    'quotation_total_label' => 'Quotation Total',
    'quotation_total_label_ur' => 'کوٹیشن کل رقم',
    'quotation_print_amount_label' => 'Quotation Amount',
    'quotation_print_amount_label_ur' => 'کوٹیشن رقم',

    'return_statuses' => [
        'draft' => 'Draft',
        'completed' => 'Completed',
        'void' => 'Void',
    ],

    'return_refund_methods' => [
        'cash' => 'Cash Refund',
        'customer_credit' => 'Credit to Customer Account',
        'original_payment' => 'Same as Original Payment',
    ],

    'return_refund_statuses' => [
        'pending' => 'Pending',
        'paid' => 'Refunded',
        'credited' => 'Credited to Account',
    ],
];
