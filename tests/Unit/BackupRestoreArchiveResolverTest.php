<?php

namespace Tests\Unit;

use App\Services\Backup\BackupRestoreArchiveResolver;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class BackupRestoreArchiveResolverTest extends TestCase
{
    public function test_lists_zip_files_in_import_directories(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('backups/uploads/test-restore.zip', 'zip-bytes');
        Storage::disk('local')->put('backups/uploads/readme.txt', 'ignore');
        Storage::disk('local')->put('backups/archives/archive.zip', 'zip-bytes');

        $options = app(BackupRestoreArchiveResolver::class)->options();

        $this->assertArrayHasKey('backups/uploads/test-restore.zip', $options);
        $this->assertArrayHasKey('backups/archives/archive.zip', $options);
        $this->assertArrayNotHasKey('backups/uploads/readme.txt', $options);
    }

    public function test_resolves_allowed_archive_path(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('backups/uploads/test-restore.zip', 'zip-bytes');

        $absolute = app(BackupRestoreArchiveResolver::class)->absolutePath('backups/uploads/test-restore.zip');

        $this->assertSame(
            Storage::disk('local')->path('backups/uploads/test-restore.zip'),
            $absolute,
        );
    }

    public function test_rejects_path_traversal(): void
    {
        Storage::fake('local');

        $this->expectException(RuntimeException::class);

        app(BackupRestoreArchiveResolver::class)->absolutePath('../outside.zip');
    }

    public function test_rejects_disallowed_directory(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('other/secret.zip', 'zip-bytes');

        $this->expectException(RuntimeException::class);

        app(BackupRestoreArchiveResolver::class)->absolutePath('other/secret.zip');
    }
}
