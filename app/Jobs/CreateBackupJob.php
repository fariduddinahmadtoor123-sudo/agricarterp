<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Services\Backup\BackupOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    /**
     * @param  list<string>  $destinations
     */
    public function __construct(
        public int $backupId,
        public array $destinations = ['local'],
    ) {}

    public function handle(BackupOrchestrator $orchestrator): void
    {
        $backup = Backup::query()->findOrFail($this->backupId);

        $orchestrator->run($backup, $this->destinations);
    }
}
