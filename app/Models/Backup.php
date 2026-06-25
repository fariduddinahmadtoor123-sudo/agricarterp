<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Backup extends Model
{
    public const TYPE_FULL = 'full';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_SCHEDULED = 'scheduled';

    protected $fillable = [
        'uuid',
        'type',
        'status',
        'trigger',
        'schedule_id',
        'file_name',
        'local_path',
        'google_drive_file_id',
        'file_size_bytes',
        'checksum_sha256',
        'manifest_version',
        'modules_included',
        'error_message',
        'started_at',
        'completed_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'modules_included' => 'array',
            'file_size_bytes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(BackupSchedule::class, 'schedule_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BackupLog::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }
}
