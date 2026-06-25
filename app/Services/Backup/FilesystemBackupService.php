<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

class FilesystemBackupService
{
    public function __construct(
        protected BackupLogger $logger,
    ) {}

    /**
     * @return list<string> absolute file paths
     */
    public function collectFiles(): array
    {
        $files = [];

        foreach ($this->rootsToBackup() as $root) {
            if (! File::isDirectory($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $path = $file->getPathname();

                if ($this->shouldSkipPath($path)) {
                    continue;
                }

                $files[] = $path;
            }
        }

        $this->logger->info('filesystem', 'Collected files for backup.', [
            'file_count' => count($files),
        ]);

        return $files;
    }

    /**
     * @param  list<string>  $files
     */
    public function addFilesToZip(ZipArchive $zip, array $files): void
    {
        foreach ($files as $absolutePath) {
            $relative = $this->relativeStoragePath($absolutePath);

            if ($relative === null) {
                continue;
            }

            if (! $zip->addFile($absolutePath, 'storage/' . $relative)) {
                throw new RuntimeException("Failed adding file to archive [{$relative}].");
            }
        }
    }

    /**
     * @return list<string>
     */
    protected function rootsToBackup(): array
    {
        return array_values(array_filter([
            config('backup.storage_paths.private_root'),
            config('backup.storage_paths.public_root'),
        ]));
    }

    protected function shouldSkipPath(string $path): bool
    {
        $normalized = Str::replace('\\', '/', $path);

        return str_contains($normalized, '/backups/')
            || str_contains($normalized, '/framework/cache/')
            || str_contains($normalized, '/framework/sessions/')
            || str_contains($normalized, '/framework/views/')
            || str_contains($normalized, '/logs/');
    }

    public function relativeStoragePath(string $absolutePath): ?string
    {
        $normalized = Str::replace('\\', '/', $absolutePath);
        $privateRoot = Str::replace('\\', '/', (string) config('backup.storage_paths.private_root'));
        $publicRoot = Str::replace('\\', '/', (string) config('backup.storage_paths.public_root'));

        if (str_starts_with($normalized, $privateRoot)) {
            return 'private/' . ltrim(substr($normalized, strlen($privateRoot)), '/');
        }

        if (str_starts_with($normalized, $publicRoot)) {
            return 'public/' . ltrim(substr($normalized, strlen($publicRoot)), '/');
        }

        return null;
    }
}
