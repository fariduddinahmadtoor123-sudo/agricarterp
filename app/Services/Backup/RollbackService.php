<?php

namespace App\Services\Backup;

use App\Models\RestoreRun;
use App\Models\RestoreSnapshot;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class RollbackService
{
    public function __construct(
        protected DatabaseRestoreService $databaseRestore,
        protected BackupArchiveService $archiveService,
        protected BackupLogger $logger,
    ) {}

    public function createSnapshot(RestoreRun $restoreRun): RestoreSnapshot
    {
        $snapshotRoot = $this->snapshotDirectory($restoreRun->uuid);
        File::ensureDirectoryExists($snapshotRoot);

        $databaseArchive = $snapshotRoot . '/database.zip';
        $storageArchive = $snapshotRoot . '/storage.zip';

        $this->logger->forRestore($restoreRun)->info('snapshot', 'Creating pre-restore database snapshot...');
        $this->snapshotDatabase($snapshotRoot);

        $this->logger->forRestore($restoreRun)->info('snapshot', 'Creating pre-restore storage snapshot...');
        $this->snapshotStorage($storageArchive);

        return RestoreSnapshot::query()->create([
            'restore_run_id' => $restoreRun->id,
            'database_path' => $snapshotRoot,
            'storage_path' => File::exists($storageArchive) ? $storageArchive : null,
            'expires_at' => now()->addDays(3),
        ]);
    }

    public function rollback(RestoreRun $restoreRun): void
    {
        $snapshot = $restoreRun->snapshot;

        if ($snapshot === null) {
            throw new RuntimeException('No pre-restore snapshot is available for rollback.');
        }

        $logger = $this->logger->forRestore($restoreRun);
        $logger->warning('rollback', 'Restore failed. Rolling back to pre-restore snapshot...');

        if (File::isDirectory($snapshot->database_path)) {
            $this->databaseRestore->importFromDirectory($snapshot->database_path);
        }

        if ($snapshot->storage_path && File::exists($snapshot->storage_path)) {
            $this->restoreStorageArchive($snapshot->storage_path);
        }

        $restoreRun->update([
            'status' => RestoreRun::STATUS_ROLLED_BACK,
            'completed_at' => now(),
        ]);

        $logger->info('rollback', 'Rollback completed.');
    }

    protected function snapshotDatabase(string $snapshotRoot): void
    {
        $databaseDirectory = $snapshotRoot . '/database';
        File::ensureDirectoryExists($databaseDirectory);
        app(DatabaseBackupService::class)->exportToDirectory($snapshotRoot);
    }

    protected function snapshotStorage(string $storageArchive): void
    {
        $roots = array_filter([
            config('backup.storage_paths.private_root'),
            config('backup.storage_paths.public_root'),
        ]);

        $zip = new ZipArchive;

        if ($zip->open($storageArchive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create storage snapshot archive.');
        }

        foreach ($roots as $root) {
            if (! File::isDirectory($root)) {
                continue;
            }

            foreach (File::allFiles($root) as $file) {
                $relative = Str::replace('\\', '/', Str::after($file->getPathname(), $root . DIRECTORY_SEPARATOR));
                $zip->addFile($file->getPathname(), basename($root) . '/' . $relative);
            }
        }

        $zip->close();
    }

    protected function restoreStorageArchive(string $storageArchive): void
    {
        $tempDirectory = dirname($storageArchive) . '/storage-restore';
        File::deleteDirectory($tempDirectory);
        File::ensureDirectoryExists($tempDirectory);

        $this->archiveService->extractToDirectory($storageArchive, $tempDirectory);

        foreach (['private', 'public'] as $segment) {
            $source = $tempDirectory . '/' . $segment;
            $target = storage_path('app/' . $segment);

            if (! File::isDirectory($source)) {
                continue;
            }

            File::copyDirectory($source, $target);
        }

        File::deleteDirectory($tempDirectory);
    }

    protected function snapshotDirectory(string $uuid): string
    {
        return storage_path('app/' . trim(config('backup.snapshot_directory', 'backups/snapshots'), '/') . '/' . $uuid);
    }
}
