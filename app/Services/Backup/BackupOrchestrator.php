<?php

namespace App\Services\Backup;

use App\Models\Backup;
use App\Services\Backup\Storage\GoogleDriveBackupStorage;
use App\Services\Backup\Storage\LocalBackupStorage;
use Illuminate\Support\Facades\File;
use RuntimeException;

class BackupOrchestrator
{
    public function __construct(
        protected DatabaseBackupService $databaseBackup,
        protected FilesystemBackupService $filesystemBackup,
        protected BackupManifestBuilder $manifestBuilder,
        protected BackupArchiveService $archiveService,
        protected LocalBackupStorage $localStorage,
        protected GoogleDriveBackupStorage $googleDriveStorage,
    ) {}

    /**
     * @param  list<string>  $destinations
     */
    public function run(Backup $backup, array $destinations = ['local']): void
    {
        $logger = app(BackupLogger::class)->forBackup($backup);
        app()->instance(BackupLogger::class, $logger);

        $workRoot = $this->workingDirectory($backup->uuid);
        $zipPath = storage_path('app/' . trim(config('backup.working_directory', 'backups/working'), '/') . '/' . $backup->uuid . '.zip');

        try {
            $backup->update([
                'status' => Backup::STATUS_RUNNING,
                'started_at' => now(),
                'error_message' => null,
            ]);

            $logger->info('start', 'Backup started.');

            if (File::exists($workRoot)) {
                File::deleteDirectory($workRoot);
            }

            File::ensureDirectoryExists($workRoot);

            $logger->info('database', 'Exporting database...');
            $this->databaseBackup->exportToDirectory($workRoot);

            $logger->info('filesystem', 'Collecting uploads and images...');
            $files = $this->filesystemBackup->collectFiles();
            $this->mirrorFilesIntoWorkspace($workRoot, $files);

            $manifest = $this->manifestBuilder->build($workRoot, $files);
            File::put($workRoot . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $logger->info('archive', 'Creating ZIP archive...');
            $size = $this->archiveService->createFromDirectory($workRoot, $zipPath);

            $checksum = hash_file('sha256', $zipPath) ?: null;
            $fileName = $backup->file_name ?: $this->defaultFileName();
            $localPath = null;
            $googleDriveFileId = null;

            if (in_array('local', $destinations, true)) {
                $logger->info('storage', 'Saving backup to local storage...');
                $backup->file_name = $fileName;
                $localPath = $this->localStorage->storeCompletedArchive($zipPath, $backup);
            }

            if (in_array('google_drive', $destinations, true)) {
                if (! $this->googleDriveStorage->isConfigured()) {
                    throw new RuntimeException('Google Drive backup is enabled but not configured.');
                }

                $logger->info('storage', 'Uploading backup to Google Drive...');
                $googleDriveFileId = $this->googleDriveStorage->uploadFile($zipPath, $fileName);
            }

            $backup->update([
                'status' => Backup::STATUS_COMPLETED,
                'file_name' => $fileName,
                'local_path' => $localPath,
                'google_drive_file_id' => $googleDriveFileId,
                'file_size_bytes' => $size,
                'checksum_sha256' => $checksum,
                'manifest_version' => (string) config('backup.format_version', '1.0'),
                'modules_included' => config('backup.modules', []),
                'completed_at' => now(),
            ]);

            $logger->info('complete', 'Backup completed successfully.', [
                'file_name' => $fileName,
                'bytes' => $backup->file_size_bytes,
            ]);
        } catch (\Throwable $exception) {
            $backup->update([
                'status' => Backup::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            $logger->error('failed', $exception->getMessage());

            throw $exception;
        } finally {
            if (File::exists($workRoot)) {
                File::deleteDirectory($workRoot);
            }

            if (File::exists($zipPath)) {
                File::delete($zipPath);
            }
        }
    }

    /**
     * @param  list<string>  $files
     */
    protected function mirrorFilesIntoWorkspace(string $workRoot, array $files): void
    {
        foreach ($files as $absolutePath) {
            $relative = $this->filesystemBackup->relativeStoragePath($absolutePath);

            if ($relative === null) {
                continue;
            }

            $target = $workRoot . '/storage/' . $relative;
            File::ensureDirectoryExists(dirname($target));
            File::copy($absolutePath, $target);
        }
    }

    protected function workingDirectory(string $uuid): string
    {
        return storage_path('app/' . trim(config('backup.working_directory', 'backups/working'), '/') . '/' . $uuid);
    }

    protected function defaultFileName(): string
    {
        return BackupFileNameGenerator::next();
    }
}
