<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class ProcessBackupQueue implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function handle(): void
    {
        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--max-time' => 3540,
            '--tries' => 1,
        ]);
    }
}
