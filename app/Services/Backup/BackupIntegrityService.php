<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use RuntimeException;

class BackupIntegrityService
{
    /**
     * @return list<string> validation errors
     */
    public function validateExtractedBackup(string $directory): array
    {
        $errors = [];

        $manifestPath = $directory . '/manifest.json';

        if (! File::exists($manifestPath)) {
            return ['manifest.json is missing from the backup archive.'];
        }

        $manifest = json_decode(File::get($manifestPath), true);

        if (! is_array($manifest)) {
            return ['manifest.json is invalid JSON.'];
        }

        if (($manifest['format_version'] ?? null) !== config('backup.format_version', '1.0')) {
            $errors[] = 'Backup format version is not compatible with this ERP version.';
        }

        $checksumFile = $directory . '/' . ($manifest['checksums_file'] ?? 'checksums.sha256.json');

        if (! File::exists($checksumFile)) {
            $errors[] = 'Checksum file is missing from the backup archive.';

            return $errors;
        }

        $checksums = json_decode(File::get($checksumFile), true);

        if (! is_array($checksums)) {
            $errors[] = 'Checksum file is invalid JSON.';

            return $errors;
        }

        foreach ($checksums as $relativePath => $expectedChecksum) {
            $absolutePath = $directory . '/' . ltrim((string) $relativePath, '/');

            if (! File::exists($absolutePath)) {
                $errors[] = "Missing file in archive: {$relativePath}";

                continue;
            }

            $actual = hash_file('sha256', $absolutePath);

            if (! hash_equals((string) $expectedChecksum, $actual)) {
                $errors[] = "Checksum mismatch: {$relativePath}";
            }
        }

        if (! File::isDirectory($directory . '/database')) {
            $errors[] = 'Database backup folder is missing.';
        }

        return $errors;
    }

    public function assertValid(string $directory): void
    {
        $errors = $this->validateExtractedBackup($directory);

        if ($errors !== []) {
            throw new RuntimeException(implode(' ', $errors));
        }
    }
}
