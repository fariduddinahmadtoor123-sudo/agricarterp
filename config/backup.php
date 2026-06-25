<?php

return [

    'format_version' => '1.0',

    'local_disk' => env('BACKUP_LOCAL_DISK', 'local'),

    'local_directory' => env('BACKUP_LOCAL_DIRECTORY', 'backups/archives'),

    'working_directory' => env('BACKUP_WORKING_DIRECTORY', 'backups/working'),

    'snapshot_directory' => env('BACKUP_SNAPSHOT_DIRECTORY', 'backups/snapshots'),

    'upload_directory' => env('BACKUP_UPLOAD_DIRECTORY', 'backups/uploads'),

    'chunk_rows' => (int) env('BACKUP_CHUNK_ROWS', 1000),

    'schedule_enabled' => (bool) env('BACKUP_SCHEDULE_ENABLED', true),

    'google_drive' => [
        'enabled' => (bool) env('BACKUP_GOOGLE_DRIVE_ENABLED', false),
        'service_account_json' => env('BACKUP_GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON'),
        'folder_id' => env('BACKUP_GOOGLE_DRIVE_FOLDER_ID'),
    ],

    'exclude_tables' => [
        'migrations',
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
        'sessions',
        'password_reset_tokens',
        'backups',
        'backup_logs',
        'backup_schedules',
        'restore_runs',
        'restore_snapshots',
    ],

    'modules' => [
        'core',
        'product-catalog',
        'contacts',
        'purchasing-inventory',
        'settings',
        'users-roles',
        'filesystem',
    ],

    'storage_paths' => [
        'private_root' => storage_path('app/private'),
        'public_root' => storage_path('app/public'),
    ],

];
