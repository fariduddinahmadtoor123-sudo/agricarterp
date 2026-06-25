<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestoreSnapshot extends Model
{
    protected $fillable = [
        'restore_run_id',
        'database_path',
        'storage_path',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function restoreRun(): BelongsTo
    {
        return $this->belongsTo(RestoreRun::class);
    }
}
