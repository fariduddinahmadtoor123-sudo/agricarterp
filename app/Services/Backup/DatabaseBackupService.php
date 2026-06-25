<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DatabaseBackupService
{
    public function __construct(
        protected BackupLogger $logger,
    ) {}

    /**
     * @return list<string>
     */
    public function tablesToBackup(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $exclude = array_flip(config('backup.exclude_tables', []));

        $tables = match ($driver) {
            'mysql' => collect(DB::select('SHOW TABLES'))
                ->map(fn ($row): string => (string) array_values((array) $row)[0])
                ->all(),
            'sqlite' => collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
                ->map(fn ($row): string => (string) $row->name)
                ->all(),
            default => Schema::getTableListing(),
        };

        return array_values(array_filter(
            $tables,
            fn (string $table): bool => ! isset($exclude[$table]),
        ));
    }

    public function exportToDirectory(string $directory, ?callable $onProgress = null): void
    {
        File::ensureDirectoryExists($directory . '/database/tables');

        if ($this->tryMysqldumpExport($directory)) {
            $this->logger->info('database', 'Database exported using mysqldump.');

            return;
        }

        $tables = $this->tablesToBackup();
        $total = count($tables);

        foreach ($tables as $index => $table) {
            $this->exportTable($table, $directory . '/database/tables/' . $table . '.sql.gz');
            $onProgress?->invoke($table, $index + 1, $total);
        }

        $this->logger->info('database', 'Database exported using chunked PHP exporter.', [
            'table_count' => $total,
        ]);
    }

    protected function tryMysqldumpExport(string $directory): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        $mysqldump = $this->findMysqldump();

        if ($mysqldump === null) {
            return false;
        }

        $config = DB::connection()->getConfig();
        $outputFile = $directory . '/database/full.sql.gz';
        File::ensureDirectoryExists(dirname($outputFile));

        $tables = implode(' ', array_map(
            escapeshellarg(...),
            $this->tablesToBackup(),
        ));

        $command = sprintf(
            '%s --single-transaction --quick --skip-lock-tables -h%s -P%s -u%s %s %s 2>&1 | gzip > %s',
            escapeshellarg($mysqldump),
            escapeshellarg((string) ($config['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($config['port'] ?? '3306')),
            escapeshellarg((string) ($config['username'] ?? 'root')),
            ! empty($config['password'])
                ? '-p' . escapeshellarg((string) $config['password'])
                : '',
            escapeshellarg((string) ($config['database'] ?? '')),
            $tables,
            escapeshellarg($outputFile),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || ! File::exists($outputFile) || File::size($outputFile) === 0) {
            if (File::exists($outputFile)) {
                File::delete($outputFile);
            }

            $this->logger->warning('database', 'mysqldump export failed; falling back to chunked exporter.', [
                'exit_code' => $exitCode,
                'output' => implode("\n", $output),
            ]);

            return false;
        }

        return true;
    }

    protected function exportTable(string $table, string $gzipPath): void
    {
        $handle = gzopen($gzipPath, 'wb9');

        if ($handle === false) {
            throw new RuntimeException("Unable to create database export file [{$gzipPath}].");
        }

        gzwrite($handle, DB::connection()->getDriverName() === 'mysql'
            ? "SET FOREIGN_KEY_CHECKS=0;\n"
            : "PRAGMA foreign_keys = OFF;\n");

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $create = DB::selectOne('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');
            $createSql = (string) ($create->{'Create Table'} ?? '');
            gzwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n\n");
        }

        $chunkSize = (int) config('backup.chunk_rows', 1000);
        $offset = 0;

        while (true) {
            $rows = DB::table($table)->offset($offset)->limit($chunkSize)->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $columns = array_keys((array) $row);
                $values = array_map(
                    fn ($value): string => $this->quoteValue($value),
                    array_values((array) $row),
                );

                $columnList = implode('`, `', $columns);
                $valueList = implode(', ', $values);
                gzwrite($handle, "INSERT INTO `{$table}` (`{$columnList}`) VALUES ({$valueList});\n");
            }

            $offset += $chunkSize;
        }

        gzwrite($handle, DB::connection()->getDriverName() === 'mysql'
            ? "SET FOREIGN_KEY_CHECKS=1;\n"
            : "PRAGMA foreign_keys = ON;\n");
        gzclose($handle);
    }

    protected function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return DB::getPdo()->quote($value);
        }

        if (is_array($value) || is_object($value)) {
            return DB::getPdo()->quote(json_encode($value));
        }

        return DB::getPdo()->quote((string) $value);
    }

    protected function findMysqldump(): ?string
    {
        foreach (['mysqldump', 'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqldump.exe'] as $candidate) {
            $command = stripos(PHP_OS_FAMILY, 'Windows') === 0
                ? 'where ' . escapeshellarg($candidate) . ' 2>nul'
                : 'command -v ' . escapeshellarg($candidate) . ' 2>/dev/null';

            exec($command, $output, $exitCode);

            if ($exitCode === 0 && isset($output[0]) && $output[0] !== '') {
                return trim($output[0]);
            }

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
