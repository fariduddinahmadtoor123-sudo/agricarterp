<?php

namespace App\Services\Settings;

use App\Models\AiSetting;
use Illuminate\Support\Facades\Crypt;

use App\Services\Ai\OpenRouterModelCatalog;

class AiSettingResolver
{
    public function record(): AiSetting
    {
        return AiSetting::query()->firstOrCreate([], [
            'vision_model' => (string) config('ai.openrouter.model', 'google/gemini-2.5-flash'),
            'enrichment_enabled' => (bool) config('ai.enabled', true),
            'batch_limit' => (int) config('ai.batch_limit', 50),
        ]);
    }

    public function isEnabled(): bool
    {
        if (! (bool) config('ai.enabled', true)) {
            return false;
        }

        return (bool) $this->record()->enrichment_enabled;
    }

    public function batchLimit(): int
    {
        $limit = (int) $this->record()->batch_limit;

        return $limit > 0 ? $limit : (int) config('ai.batch_limit', 50);
    }

    public function visionModel(): string
    {
        $model = trim((string) $this->record()->vision_model);

        if ($model !== '') {
            return $model;
        }

        return (string) config('ai.openrouter.model', 'google/gemini-2.5-flash');
    }

    /**
     * @return array<string, string>
     */
    public function visionModelOptions(): array
    {
        return app(OpenRouterModelCatalog::class)->options();
    }

    public function resolvedVisionModel(): string
    {
        return app(OpenRouterModelCatalog::class)->resolveModel($this->visionModel());
    }

    public function hasApiKey(): bool
    {
        return filled($this->apiKey());
    }

    public function apiKey(): ?string
    {
        $encrypted = $this->record()->openrouter_api_key;

        if (filled($encrypted)) {
            try {
                return Crypt::decryptString((string) $encrypted);
            } catch (\Throwable) {
                return null;
            }
        }

        $envKey = config('ai.openrouter.api_key');

        return filled($envKey) ? (string) $envKey : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function formState(): array
    {
        $record = $this->record();

        return [
            'openrouter_api_key' => '',
            'vision_model' => $this->visionModel(),
            'enrichment_enabled' => (bool) $record->enrichment_enabled,
            'batch_limit' => $this->batchLimit(),
        ];
    }
}
