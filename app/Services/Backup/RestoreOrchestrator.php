<?php

namespace App\Services\Backup;

use App\Models\Backup;
use App\Models\RestoreRun;
use App\Services\Backup\Storage\LocalBackupStorage;
use App\Services\Users\UserAccessRepairService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;

class RestoreOrchestrator
{
    public function __construct(
        protected BackupArchiveService $archiveService,
        protected BackupIntegrityService $integrityService,
        protected DatabaseRestoreService $databaseRestore,
        protected RollbackService $rollbackService,
        protected LocalBackupStorage $localStorage,
    ) {}

    public function runFromBackupRecord(RestoreRun $restoreRun, Backup $backup): void
    {
        if (! $backup->local_path) {
            throw new RuntimeException('This backup does not have a local archive to restore.');
        }

        $absolute = $this->localStorage->absolutePath($backup->local_path);

        if (! File::exists($absolute)) {
            throw new RuntimeException('Backup archive file was not found on disk.');
        }

        $this->run($restoreRun, $absolute);
    }

    public function runFromUploadedArchive(RestoreRun $restoreRun, string $absoluteArchivePath): void
    {
        $this->run($restoreRun, $absoluteArchivePath);
    }

    protected function run(RestoreRun $restoreRun, string $archivePath): void
    {
        $logger = app(BackupLogger::class)->forRestore($restoreRun);
        app()->instance(BackupLogger::class, $logger);

        $extractDirectory = storage_path('app/' . trim(config('backup.working_directory', 'backups/working'), '/') . '/restore-' . $restoreRun->uuid);

        try {
            $restoreRun->update([
                'status' => RestoreRun::STATUS_VALIDATING,
                'started_at' => now(),
                'source_path' => $archivePath,
                'error_message' => null,
            ]);

            $logger->info('validate', 'Validating backup archive...');

            if (File::exists($extractDirectory)) {
                File::deleteDirectory($extractDirectory);
            }

            File::ensureDirectoryExists($extractDirectory);
            $this->archiveService->extractToDirectory($archivePath, $extractDirectory);
            $this->integrityService->assertValid($extractDirectory);

            $restoreRun->update(['status' => RestoreRun::STATUS_RUNNING]);
            Artisan::call('down', ['--retry' => 60]);

            $snapshot = $this->rollbackService->createSnapshot($restoreRun);
            $restoreRun->setRelation('snapshot', $snapshot);

            $logger->info('database', 'Importing database from backup...');
            $this->databaseRestore->importFromDirectory($extractDirectory);

            $logger->info('storage', 'Restoring files from backup...');
            $this->restoreStorageFromExtract($extractDirectory);

            Artisan::call('up');

            app(UserAccessRepairService::class)->repair();
            $logger->info('access', 'User roles and permissions repaired after restore.');

            $restoreRun->update([
                'status' => RestoreRun::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $logger->info('complete', 'Restore completed successfully.');
        } catch (\Throwable $exception) {
            $logger->error('failed', $exception->getMessage());

            try {
                if ($restoreRun->snapshot) {
                    $this->rollbackService->rollback($restoreRun);
                }
            } catch (\Throwable $rollbackException) {
                $logger->error('rollback', $rollbackException->getMessage());
            }

            if (app()->isDownForMaintenance()) {
                Artisan::call('up');
            }

            $restoreRun->update([
                'status' => RestoreRun::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        } finally {
            if (File::exists($extractDirectory)) {
                File::deleteDirectory($extractDirectory);
            }
        }
    }

    protected function restoreStorageFromExtract(string $extractDirectory): void
    {
        $storageDirectory = $extractDirectory . '/storage';

        if (! File::isDirectory($storageDirectory)) {
            return;
        }

        foreach (['private', 'public'] as $segment) {
            $source = $storageDirectory . '/' . $segment;
            $target = storage_path('app/' . $segment);

            if (! File::isDirectory($source)) {
                continue;
            }

            File::ensureDirectoryExists($target);
            File::copyDirectory($source, $target);
        }
    }
}
