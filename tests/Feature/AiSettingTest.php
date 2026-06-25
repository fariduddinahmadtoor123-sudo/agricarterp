<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Services\Settings\AiSettingPersistenceService;
use App\Services\Settings\AiSettingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class AiSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_encrypted_openrouter_key_and_model(): void
    {
        app(AiSettingPersistenceService::class)->save([
            'openrouter_api_key' => 'sk-or-test-key',
            'vision_model' => 'openai/gpt-4o',
            'enrichment_enabled' => true,
            'batch_limit' => 25,
        ]);

        $resolver = app(AiSettingResolver::class);

        $this->assertTrue($resolver->hasApiKey());
        $this->assertSame('sk-or-test-key', $resolver->apiKey());
        $this->assertSame('openai/gpt-4o', $resolver->visionModel());
        $this->assertSame(25, $resolver->batchLimit());

        $stored = AiSetting::query()->first();
        $this->assertNotSame('sk-or-test-key', $stored?->openrouter_api_key);
        $this->assertSame('sk-or-test-key', Crypt::decryptString((string) $stored?->openrouter_api_key));
    }
}
