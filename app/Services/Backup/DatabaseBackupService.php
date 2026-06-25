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

        $tables = $this->tablesToBackup();
        $total = count($tables);

        foreach ($tables as $index => $table) {
            $this->exportTable($table, $directory . '/database/tables/' . $table . '.sql.gz');
            $onProgress?->invoke($table, $index + 1, $total);
        }

        $this->logger->info('database', 'Database exported using PHP exporter.', [
            'table_count' => $total,
        ]);
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
}
