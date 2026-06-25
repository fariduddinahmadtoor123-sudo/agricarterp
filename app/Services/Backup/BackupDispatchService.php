<?php

namespace App\Services\Backup;

use App\Jobs\CreateBackupJob;
use App\Jobs\ProcessBackupQueue;
use App\Jobs\RestoreBackupJob;
use App\Models\Backup;
use App\Models\RestoreRun;

class BackupDispatchService
{
    /**
     * @param  list<string>  $destinations
     */
    public function dispatchCreate(Backup $backup, array $destinations): void
    {
        CreateBackupJob::dispatch($backup->id, $destinations);
        dispatch(new ProcessBackupQueue())->afterResponse();
    }

    public function dispatchRestore(RestoreRun $restoreRun, ?int $backupId = null, ?string $uploadedArchivePath = null): void
    {
        RestoreBackupJob::dispatch($restoreRun->id, $backupId, $uploadedArchivePath);
        dispatch(new ProcessBackupQueue())->afterResponse();
    }
}
