<?php

namespace App\Services\Backup;

use App\Models\Backup;
use App\Models\BackupLog;
use App\Models\RestoreRun;
use Illuminate\Support\Facades\Log;

class BackupLogger
{
    public function __construct(
        protected ?int $backupId = null,
        protected ?int $restoreRunId = null,
    ) {}

    public function forBackup(Backup $backup): self
    {
        $clone = clone $this;
        $clone->backupId = $backup->id;

        return $clone;
    }

    public function forRestore(RestoreRun $restoreRun): self
    {
        $clone = clone $this;
        $clone->restoreRunId = $restoreRun->id;

        return $clone;
    }

    public function info(string $step, string $message, array $context = []): void
    {
        $this->write(BackupLog::LEVEL_INFO, $step, $message, $context);
    }

    public function warning(string $step, string $message, array $context = []): void
    {
        $this->write(BackupLog::LEVEL_WARNING, $step, $message, $context);
    }

    public function error(string $step, string $message, array $context = []): void
    {
        $this->write(BackupLog::LEVEL_ERROR, $step, $message, $context);
    }

    protected function write(string $level, string $step, string $message, array $context): void
    {
        BackupLog::query()->create([
            'backup_id' => $this->backupId,
            'restore_run_id' => $this->restoreRunId,
            'level' => $level,
            'step' => $step,
            'message' => $message,
            'context' => $context === [] ? null : $context,
        ]);

        Log::channel('single')->log($level, "[backup:{$step}] {$message}", $context);
    }
}
