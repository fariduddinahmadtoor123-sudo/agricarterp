<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Brand;
use App\Models\User;
use App\Services\Backup\BackupOrchestrator;
use App\Services\Backup\BackupIntegrityService;
use App\Services\Backup\BackupArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_backup_creates_valid_zip_archive(): void
    {
        $user = User::factory()->create();

        Brand::query()->create([
            'brand_number' => 'BR-0001',
            'name_en' => 'Test Brand',
            'name_ur' => 'برانڈ',
            'short_note' => 'Test',
            'status' => 'active',
        ]);

        $backup = Backup::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => Backup::TYPE_FULL,
            'status' => Backup::STATUS_PENDING,
            'trigger' => Backup::TRIGGER_MANUAL,
            'file_name' => 'test-backup.zip',
            'created_by_user_id' => $user->id,
        ]);

        app(BackupOrchestrator::class)->run($backup->fresh(), ['local']);

        $backup->refresh();

        $this->assertSame(Backup::STATUS_COMPLETED, $backup->status);
        $this->assertNotNull($backup->local_path);
        $this->assertNotNull($backup->checksum_sha256);
        $this->assertGreaterThan(0, $backup->file_size_bytes);
        $this->assertDatabaseHas('backup_logs', [
            'backup_id' => $backup->id,
            'step' => 'complete',
        ]);

        $archivePath = storage_path('app/private/' . $backup->local_path);
        $this->assertFileExists($archivePath);

        $extractDirectory = storage_path('app/backups/working/test-extract-' . $backup->id);
        File::deleteDirectory($extractDirectory);
        File::ensureDirectoryExists($extractDirectory);

        app(BackupArchiveService::class)->extractToDirectory($archivePath, $extractDirectory);
        $errors = app(BackupIntegrityService::class)->validateExtractedBackup($extractDirectory);

        $this->assertSame([], $errors, implode(' ', $errors));

        File::deleteDirectory($extractDirectory);
    }

    public function test_backups_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get('/admin/settings/system-backups')
            ->assertOk()
            ->assertSee('Backups');
    }
}
