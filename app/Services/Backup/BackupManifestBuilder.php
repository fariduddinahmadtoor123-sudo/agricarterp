<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BackupManifestBuilder
{
    /**
     * @param  list<string>  $storageFiles absolute paths
     * @return array<string, mixed>
     */
    public function build(string $workDirectory, array $storageFiles): array
    {
        $checksums = [];
        $this->appendDirectoryChecksums($workDirectory, $workDirectory, $checksums);

        $checksumPath = $workDirectory . '/checksums.sha256.json';
        File::put($checksumPath, json_encode($checksums, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $databaseBytes = $this->directorySize($workDirectory . '/database');
        $storageBytes = $this->sumFileSizes($storageFiles);

        return [
            'format_version' => config('backup.format_version', '1.0'),
            'agricart_version' => (string) config('agricart.brand.name', 'Agricart ERP'),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'app_env' => config('app.env'),
            'created_at' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'backup_type' => 'full',
            'database' => [
                'driver' => config('database.default'),
                'table_count' => count(app(DatabaseBackupService::class)->tablesToBackup()),
                'bytes' => $databaseBytes,
            ],
            'storage' => [
                'file_count' => count($storageFiles),
                'bytes' => $storageBytes,
                'checksum_algorithm' => 'sha256',
            ],
            'modules' => config('backup.modules', []),
            'checksums_file' => 'checksums.sha256.json',
        ];
    }

    /**
     * @param  array<string, string>  $checksums
     */
    protected function appendDirectoryChecksums(string $baseDirectory, string $directory, array &$checksums): void
    {
        if (! File::isDirectory($directory)) {
            return;
        }

        foreach (File::allFiles($directory) as $file) {
            $relative = Str::replace('\\', '/', Str::after($file->getPathname(), $baseDirectory . DIRECTORY_SEPARATOR));

            if ($relative === 'checksums.sha256.json' || $relative === 'manifest.json') {
                continue;
            }

            $checksums[$relative] = hash_file('sha256', $file->getPathname());
        }
    }

    /**
     * @param  list<string>  $files
     */
    protected function sumFileSizes(array $files): int
    {
        $total = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                $total += filesize($file) ?: 0;
            }
        }

        return $total;
    }

    protected function directorySize(string $directory): int
    {
        if (! File::isDirectory($directory)) {
            return 0;
        }

        $total = 0;

        foreach (File::allFiles($directory) as $file) {
            $total += $file->getSize();
        }

        return $total;
    }
}
