<?php

namespace App\Console\Commands;

use App\Jobs\ProcessBackupQueue;
use App\Services\Backup\BackupScheduleService;
use Illuminate\Console\Command;

class RunScheduledBackupsCommand extends Command
{
    protected $signature = 'backup:run-scheduled';

    protected $description = 'Run due automatic Agricart backup schedules';

    public function handle(BackupScheduleService $schedules): int
    {
        $count = $schedules->runDueSchedules();

        if ($count > 0) {
            app(ProcessBackupQueue::class)->handle();
        }

        $this->info("Dispatched {$count} scheduled backup(s).");

        return self::SUCCESS;
    }
}
