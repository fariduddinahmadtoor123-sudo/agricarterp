<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DatabaseRestoreService
{
    public function __construct(
        protected BackupLogger $logger,
    ) {}

    public function importFromDirectory(string $directory): void
    {
        $fullDump = $directory . '/database/full.sql.gz';

        if (File::exists($fullDump)) {
            $this->importGzipSqlFile($fullDump);

            return;
        }

        $tablesDirectory = $directory . '/database/tables';

        if (! File::isDirectory($tablesDirectory)) {
            throw new RuntimeException('No database export found in backup archive.');
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } else {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        foreach (File::files($tablesDirectory) as $file) {
            if (! str_ends_with($file->getFilename(), '.sql.gz')) {
                continue;
            }

            $this->logger->info('database', 'Importing table dump.', ['file' => $file->getFilename()]);
            $this->importGzipSqlFile($file->getPathname());
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } else {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    public function exportSnapshot(string $targetGzipPath): void
    {
        File::ensureDirectoryExists(dirname($targetGzipPath));
        app(DatabaseBackupService::class)->exportToDirectory(dirname($targetGzipPath));

        $tablesDirectory = dirname($targetGzipPath) . '/database/tables';

        if (File::isDirectory($tablesDirectory)) {
            return;
        }

        $fullDump = dirname($targetGzipPath) . '/database/full.sql.gz';

        if (File::exists($fullDump)) {
            File::copy($fullDump, $targetGzipPath);
        }
    }

    protected function importGzipSqlFile(string $gzipPath): void
    {
        $handle = gzopen($gzipPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open SQL dump [{$gzipPath}].");
        }

        $statement = '';

        while (! gzeof($handle)) {
            $line = gzgets($handle);

            if ($line === false) {
                break;
            }

            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }

            $statement .= $line;

            if (! str_ends_with(trim($line), ';')) {
                continue;
            }

            DB::unprepared($statement);
            $statement = '';
        }

        gzclose($handle);
    }
}
