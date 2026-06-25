<?php

namespace App\Services\Backup;

use App\Models\Backup;

class BackupFileNameGenerator
{
    /**
     * Day-Month-Year sequence format, e.g. 25-06-2026-1.zip
     */
    public static function next(): string
    {
        $datePrefix = now()->format('d-m-Y');

        $sequence = Backup::query()
            ->whereDate('created_at', today())
            ->count() + 1;

        return "{$datePrefix}-{$sequence}.zip";
    }
}
