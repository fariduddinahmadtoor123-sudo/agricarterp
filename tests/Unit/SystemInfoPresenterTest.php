<?php

namespace Tests\Unit;

use App\Support\Settings\SystemInfoPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemInfoPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_rows_include_core_system_fields_without_filament_version_constant(): void
    {
        $labels = collect(app(SystemInfoPresenter::class)->rows())
            ->pluck('label')
            ->all();

        $this->assertSame([
            'Laravel Version',
            'Filament Version',
            'PHP Version',
            'MySQL Version',
            'App Environment',
            'Debug Mode',
            'Queue Status',
            'Cache Status',
            'Storage Status',
            'Last Backup',
        ], $labels);

        $filamentVersion = collect(app(SystemInfoPresenter::class)->rows())
            ->firstWhere('label', 'Filament Version')['value'];

        $this->assertNotSame('', $filamentVersion);
        $this->assertNotSame('Unknown', $filamentVersion);
    }
}
