<?php

return [
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
    ],
];
