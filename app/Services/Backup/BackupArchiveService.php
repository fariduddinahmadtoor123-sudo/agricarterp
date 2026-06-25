<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class BackupArchiveService
{
    public function createFromDirectory(string $workDirectory, string $zipPath): int
    {
        File::ensureDirectoryExists(dirname($zipPath));

        if (File::exists($zipPath)) {
            File::delete($zipPath);
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Unable to create backup archive [{$zipPath}].");
        }

        foreach (File::allFiles($workDirectory) as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($workDirectory) + 1));

            if (! $zip->addFile($file->getPathname(), $relative)) {
                $zip->close();

                throw new RuntimeException("Failed adding [{$relative}] to archive.");
            }
        }

        $zip->close();

        return File::size($zipPath) ?: 0;
    }

    public function extractToDirectory(string $zipPath, string $targetDirectory): void
    {
        File::ensureDirectoryExists($targetDirectory);

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Unable to open backup archive [{$zipPath}].");
        }

        if (! $zip->extractTo($targetDirectory)) {
            $zip->close();

            throw new RuntimeException('Backup archive extraction failed.');
        }

        $zip->close();
    }
}
