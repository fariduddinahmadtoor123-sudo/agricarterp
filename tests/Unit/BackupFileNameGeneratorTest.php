<?php

namespace Tests\Unit;

use App\Models\Backup;
use App\Services\Backup\BackupFileNameGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupFileNameGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_day_month_year_sequence_filename(): void
    {
        $this->travelTo('2026-06-25 10:00:00');

        $this->assertSame('25-06-2026-1.zip', BackupFileNameGenerator::next());

        Backup::query()->create([
            'uuid' => (string) str()->uuid(),
            'file_name' => '25-06-2026-1.zip',
            'status' => Backup::STATUS_COMPLETED,
        ]);

        $this->assertSame('25-06-2026-2.zip', BackupFileNameGenerator::next());
    }
}
