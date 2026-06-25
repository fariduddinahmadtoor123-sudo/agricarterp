<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestoreRun extends Model
{
    public const MODE_REPLACE = 'replace';

    public const STATUS_PENDING = 'pending';

    public const STATUS_VALIDATING = 'validating';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'uuid',
        'backup_id',
        'source_path',
        'mode',
        'status',
        'error_message',
        'started_at',
        'completed_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function snapshot(): HasOne
    {
        return $this->hasOne(RestoreSnapshot::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BackupLog::class);
    }
}
