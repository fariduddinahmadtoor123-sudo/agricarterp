<?php

namespace Tests\Feature;

use App\Jobs\EnrichCategoryJob;
use App\Jobs\EnrichProductJob;
use App\Models\AiSetting;
use App\Models\Category;
use App\Services\Ai\CatalogEnrichmentService;
use Illuminate\Support\Facades\Crypt;
use App\Services\ProductCatalog\CategoryPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CatalogEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.enabled' => true,
            'ai.openrouter.base_url' => 'https://openrouter.test/api/v1',
            'ai.version' => '1',
        ]);

        AiSetting::query()->create([
            'openrouter_api_key' => Crypt::encryptString('test-key'),
            'vision_model' => 'google/gemini-2.5-flash',
            'enrichment_enabled' => true,
            'batch_limit' => 50,
        ]);
    }

    public function test_command_queues_background_jobs(): void
    {
        Queue::fake();

        $category = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Spray Pump',
            'name_ur' => '',
        ]);

        $this->artisan('catalog:enrich-pending')
            ->assertSuccessful()
            ->expectsOutputToContain('Queued 1 categories');

        Queue::assertPushed(EnrichCategoryJob::class, fn (EnrichCategoryJob $job): bool => $job->categoryId === $category->id);
    }

    public function test_enrichment_fills_empty_category_fields_from_openrouter(): void
    {
        Http::fake([
            'openrouter.test/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'name_ur' => 'سپرے پمپ',
                                'description_en' => 'Agricultural spray pump for crop protection.',
                                'hs_code' => '8424.8190',
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ]),
        ]);

        $category = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Spray Pump',
            'name_ur' => '',
        ]);

        app(CatalogEnrichmentService::class)->enrichCategory($category->id);

        $category->refresh();

        $this->assertSame('سپرے پمپ', $category->name_ur);
        $this->assertSame('Agricultural spray pump for crop protection.', $category->description_en);
        $this->assertSame('8424.8190', $category->hs_code);
        $this->assertSame(Category::AI_STATUS_REVIEW, $category->ai_status);
        $this->assertNotNull($category->ai_generated_at);
        $this->assertSame('1', $category->ai_version);
    }

    public function test_enrichment_marks_failed_without_stopping_other_records(): void
    {
        Http::fake([
            'openrouter.test/*' => Http::response([
                'error' => ['message' => 'Rate limit exceeded'],
            ], 429),
        ]);

        $category = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Rotavator Gear',
            'name_ur' => '',
        ]);

        $job = new EnrichCategoryJob($category->id);
        $job->handle(app(CatalogEnrichmentService::class));

        $this->assertSame(Category::AI_STATUS_FAILED, $category->fresh()->ai_status);

        $log = \App\Models\AiEnrichmentLog::query()->first();
        $this->assertNotNull($log);
        $this->assertSame(429, $log->error_code);
        $this->assertStringContainsString('Rate limit exceeded', (string) $log->error_reason);
        $this->assertStringContainsString('Wait a few minutes', (string) $log->suggested_action);
    }

    public function test_dry_run_reports_pending_counts_without_queueing(): void
    {
        Queue::fake();

        app(CategoryPersistenceService::class)->create([
            'name_en' => 'Side Gear',
            'name_ur' => '',
        ]);

        $this->artisan('catalog:enrich-pending --dry-run')
            ->assertSuccessful()
            ->expectsOutputToContain('Dry run: 1 categories');

        Queue::assertNothingPushed();
    }

    public function test_command_fails_when_api_key_missing(): void
    {
        AiSetting::query()->update(['openrouter_api_key' => null]);
        config(['ai.openrouter.api_key' => null]);

        $this->artisan('catalog:enrich-pending')
            ->assertFailed();
    }

    public function test_product_job_can_be_queued(): void
    {
        Queue::fake();

        $this->artisan('catalog:enrich-pending --products')
            ->assertSuccessful();

        Queue::assertNotPushed(EnrichProductJob::class);
    }
}
