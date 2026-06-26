<?php

namespace App\Support\Settings;

use App\Models\Backup;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\DB;

class SystemInfoPresenter
{
    /**
     * @return list<array{label: string, value: string, tone?: string}>
     */
    public function rows(): array
    {
        return [
            ['label' => 'Laravel Version', 'value' => app()->version()],
            ['label' => 'Filament Version', 'value' => $this->filamentVersion()],
            ['label' => 'PHP Version', 'value' => PHP_VERSION],
            ['label' => 'MySQL Version', 'value' => $this->mysqlVersion()],
            ['label' => 'App Environment', 'value' => (string) config('app.env')],
            [
                'label' => 'Debug Mode',
                'value' => config('app.debug') ? 'On' : 'Off',
                'tone' => config('app.debug') ? 'warning' : null,
            ],
            ['label' => 'Queue Status', 'value' => $this->queueStatus()],
            ['label' => 'Cache Status', 'value' => $this->cacheStatus()],
            ['label' => 'Storage Status', 'value' => $this->storageStatus(), 'tone' => $this->storageIsHealthy() ? null : 'warning'],
            ['label' => 'Last Backup', 'value' => $this->lastBackupLabel()],
        ];
    }

    protected function filamentVersion(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('filament/filament')) {
            return (string) InstalledVersions::getPrettyVersion('filament/filament');
        }

        return 'Unknown';
    }

    protected function mysqlVersion(): string
    {
        $connectionName = (string) config('database.default');
        $driver = (string) config("database.connections.{$connectionName}.driver");

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return 'Not using MySQL (' . $driver . ')';
        }

        try {
            $row = DB::connection()->selectOne('select version() as version');

            return (string) ($row->version ?? 'Unknown');
        } catch (\Throwable) {
            return 'Unavailable';
        }
    }

    protected function queueStatus(): string
    {
        $driver = (string) config('queue.default');

        return match ($driver) {
            'sync' => 'Sync (runs immediately, no worker)',
            default => ucfirst($driver) . ' (configured)',
        };
    }

    protected function cacheStatus(): string
    {
        $store = (string) config('cache.default');

        try {
            cache()->store()->getStore();

            return ucfirst($store) . ' (available)';
        } catch (\Throwable) {
            return ucfirst($store) . ' (unavailable)';
        }
    }

    protected function storageStatus(): string
    {
        $disk = (string) config('filesystems.default');
        $writable = $this->storageIsHealthy();

        return ucfirst($disk) . ' disk · storage ' . ($writable ? 'writable' : 'not writable');
    }

    protected function storageIsHealthy(): bool
    {
        return is_writable(storage_path());
    }

    protected function lastBackupLabel(): string
    {
        if (! $this->backupsTableExists()) {
            return '—';
        }

        $lastBackup = Backup::query()
            ->where('status', Backup::STATUS_COMPLETED)
            ->latest('completed_at')
            ->first();

        if ($lastBackup === null) {
            return 'No completed backup yet';
        }

        $completedAt = $lastBackup->completed_at?->format('d M Y H:i') ?? '—';

        return $completedAt . ($lastBackup->file_name ? ' · ' . $lastBackup->file_name : '');
    }

    protected function backupsTableExists(): bool
    {
        try {
            Backup::query()->limit(1)->get();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
