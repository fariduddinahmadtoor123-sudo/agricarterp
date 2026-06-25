<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupLog extends Model
{
    public const UPDATED_AT = null;

    public const LEVEL_INFO = 'info';

    public const LEVEL_WARNING = 'warning';

    public const LEVEL_ERROR = 'error';

    protected $fillable = [
        'backup_id',
        'restore_run_id',
        'level',
        'step',
        'message',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }

    public function restoreRun(): BelongsTo
    {
        return $this->belongsTo(RestoreRun::class);
    }
}
