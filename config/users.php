<?php

return [
    'permission_enforced_modules' => [
        'contacts',
        'product-catalog',
        'purchasing-inventory',
        'settings',
    ],

    'document_disk' => env('USER_DOCUMENT_DISK', 'local'),

    'documents_directory' => 'users/documents',

    'application_documents_directory' => 'users/applications/documents',

    'document_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],

    'user_statuses' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'application_statuses' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],

    'system_roles' => [
        'super_admin' => 'Super Admin',
    ],

    'restore_super_admin_emails' => array_filter(array_map(
        'trim',
        explode(',', (string) env('RESTORE_SUPER_ADMIN_EMAILS', 'admin@agricarterp.com,faridurdinahmad@gmail.com')),
    )),

    /**
     * Permission matrix: module => [action => label].
     * Stored in DB as "{module}.{action}".
     */
    'permission_matrix' => [
        'contacts' => [
            'view' => 'View',
            'create' => 'Create',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'restore' => 'Restore',
        ],
        'product-catalog' => [
            'view' => 'View',
            'create' => 'Create',
            'edit' => 'Edit',
            'archive' => 'Archive',
            'restore' => 'Restore',
        ],
        'purchasing-inventory' => [
            'view' => 'View',
            'create' => 'Create',
            'edit' => 'Edit',
        ],
        'settings' => [
            'view' => 'View',
            'edit' => 'Edit',
        ],
        'approvals' => [
            'view' => 'View',
            'approve' => 'Approve',
            'reject' => 'Reject',
        ],
        'users' => [
            'view' => 'View',
            'create' => 'Create',
            'edit' => 'Edit',
            'deactivate' => 'Deactivate',
        ],
        'roles' => [
            'view' => 'View',
            'create' => 'Create',
            'edit' => 'Edit',
            'delete' => 'Delete',
        ],
        'tax-system' => [
            'view' => 'View',
            'create' => 'Create',
            'edit' => 'Edit',
            'delete' => 'Delete',
        ],
    ],
];
