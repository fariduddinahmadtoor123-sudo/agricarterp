<?php

namespace App\Services\Backup;

use App\Services\Backup\BackupDispatchService;
use App\Services\Backup\BackupFileNameGenerator;
use App\Models\Backup;
use App\Models\BackupSchedule;
use Illuminate\Support\Str;

class BackupScheduleService
{
    /**
     * @return array<string, string>
     */
    public function frequencyOptions(): array
    {
        return [
            BackupSchedule::FREQUENCY_HOURLY => 'Hourly',
            BackupSchedule::FREQUENCY_DAILY => 'Daily',
            BackupSchedule::FREQUENCY_WEEKLY => 'Weekly',
            BackupSchedule::FREQUENCY_MONTHLY => 'Monthly',
            BackupSchedule::FREQUENCY_CUSTOM => 'Custom (cron)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function destinationOptions(): array
    {
        return [
            'local' => 'Local Server',
            'google_drive' => 'Google Drive',
        ];
    }

    public function cronForSchedule(BackupSchedule $schedule): string
    {
        return match ($schedule->frequency) {
            BackupSchedule::FREQUENCY_HOURLY => '0 * * * *',
            BackupSchedule::FREQUENCY_DAILY => '0 2 * * *',
            BackupSchedule::FREQUENCY_WEEKLY => '0 3 * * 0',
            BackupSchedule::FREQUENCY_MONTHLY => '0 4 1 * *',
            BackupSchedule::FREQUENCY_CUSTOM => (string) ($schedule->cron_expression ?: '0 2 * * *'),
            default => '0 2 * * *',
        };
    }

    public function runDueSchedules(): int
    {
        $due = BackupSchedule::query()
            ->where('enabled', true)
            ->where(function ($query): void {
                $query
                    ->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        $count = 0;

        foreach ($due as $schedule) {
            $this->dispatchSchedule($schedule);
            $count++;
        }

        return $count;
    }

    public function dispatchSchedule(BackupSchedule $schedule): Backup
    {
        $backup = Backup::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => Backup::TYPE_FULL,
            'status' => Backup::STATUS_PENDING,
            'trigger' => Backup::TRIGGER_SCHEDULED,
            'schedule_id' => $schedule->id,
            'file_name' => BackupFileNameGenerator::next(),
            'manifest_version' => (string) config('backup.format_version', '1.0'),
            'modules_included' => config('backup.modules', []),
        ]);

        $destinations = $schedule->destinations ?: ['local'];

        app(BackupDispatchService::class)->dispatchCreate($backup, $destinations);

        $schedule->update([
            'last_run_at' => now(),
            'next_run_at' => now()->addHour(),
        ]);

        $this->pruneScheduleBackups($schedule);

        return $backup;
    }

    protected function pruneScheduleBackups(BackupSchedule $schedule): void
    {
        $retention = max(1, (int) $schedule->retention_count);
        $oldBackups = Backup::query()
            ->where('schedule_id', $schedule->id)
            ->where('status', Backup::STATUS_COMPLETED)
            ->orderByDesc('id')
            ->skip($retention)
            ->take(100)
            ->get();

        $localStorage = app(Storage\LocalBackupStorage::class);

        foreach ($oldBackups as $backup) {
            if ($backup->local_path) {
                $localStorage->delete($backup->local_path);
            }

            $backup->delete();
        }
    }
}
