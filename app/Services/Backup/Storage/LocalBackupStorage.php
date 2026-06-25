<?php

namespace App\Services\Backup\Storage;

use App\Models\Backup;
use Illuminate\Support\Facades\Storage;

class LocalBackupStorage
{
    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('backup.local_disk', 'local'));
    }

    public function storeCompletedArchive(string $sourcePath, Backup $backup): string
    {
        $directory = trim(config('backup.local_directory', 'backups/archives'), '/');
        $destination = $directory . '/' . $backup->file_name;

        $stream = fopen($sourcePath, 'rb');

        if ($stream === false) {
            throw new \RuntimeException('Unable to read completed backup archive.');
        }

        $this->disk()->put($destination, $stream);
        fclose($stream);

        return $destination;
    }

    public function absolutePath(string $relativePath): string
    {
        return $this->disk()->path($relativePath);
    }

    public function delete(string $relativePath): void
    {
        if ($this->disk()->exists($relativePath)) {
            $this->disk()->delete($relativePath);
        }
    }

    public function downloadStream(string $relativePath)
    {
        return $this->disk()->readStream($relativePath);
    }
}
