<?php

namespace App\Services\Backup;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupRestoreArchiveResolver
{
    public function disk(): Filesystem
    {
        return Storage::disk(config('backup.local_disk', 'local'));
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->importDirectories() as $directory) {
            $directory = trim($directory, '/');

            if (! $this->disk()->exists($directory)) {
                continue;
            }

            foreach ($this->disk()->files($directory) as $relativePath) {
                if (! $this->isZipPath($relativePath)) {
                    continue;
                }

                $options[$relativePath] = $this->formatOptionLabel($relativePath);
            }
        }

        arsort($options);

        return $options;
    }

    public function absolutePath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));

        if ($relativePath === '' || str_contains($relativePath, '..')) {
            throw new RuntimeException('Invalid backup archive path.');
        }

        if (! $this->isAllowedRelativePath($relativePath)) {
            throw new RuntimeException('Backup archive path is not in an allowed import directory.');
        }

        if (! $this->isZipPath($relativePath)) {
            throw new RuntimeException('Backup archive must be a ZIP file.');
        }

        if (! $this->disk()->exists($relativePath)) {
            throw new RuntimeException('Backup archive was not found on the server.');
        }

        return $this->disk()->path($relativePath);
    }

    /**
     * @return list<string>
     */
    protected function importDirectories(): array
    {
        return config('backup.restore.import_directories', [
            'backups/uploads',
            'backups/archives',
        ]);
    }

    protected function isAllowedRelativePath(string $relativePath): bool
    {
        foreach ($this->importDirectories() as $directory) {
            $directory = trim(str_replace('\\', '/', $directory), '/');

            if ($relativePath === $directory || str_starts_with($relativePath, $directory . '/')) {
                return true;
            }
        }

        return false;
    }

    protected function isZipPath(string $relativePath): bool
    {
        return str_ends_with(strtolower($relativePath), '.zip');
    }

    protected function formatOptionLabel(string $relativePath): string
    {
        $size = $this->disk()->size($relativePath) ?: 0;
        $modified = $this->disk()->lastModified($relativePath);

        return basename($relativePath)
            . ' — '
            . $this->formatBytes($size)
            . ' — '
            . date('Y-m-d H:i', $modified);
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }
}
