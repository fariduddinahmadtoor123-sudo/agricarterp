<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Models\RestoreRun;
use App\Services\Backup\RestoreOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RestoreBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(
        public int $restoreRunId,
        public ?int $backupId = null,
        public ?string $uploadedArchivePath = null,
    ) {}

    public function handle(RestoreOrchestrator $orchestrator): void
    {
        $restoreRun = RestoreRun::query()->findOrFail($this->restoreRunId);

        if ($this->backupId !== null) {
            $backup = Backup::query()->findOrFail($this->backupId);
            $orchestrator->runFromBackupRecord($restoreRun, $backup);

            return;
        }

        if ($this->uploadedArchivePath !== null) {
            $orchestrator->runFromUploadedArchive($restoreRun, $this->uploadedArchivePath);

            return;
        }

        throw new \RuntimeException('Restore job is missing a backup source.');
    }
}
