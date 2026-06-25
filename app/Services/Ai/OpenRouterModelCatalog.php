<?php

namespace App\Services\Ai;

use App\Services\Settings\AiSettingResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OpenRouterModelCatalog
{
    public function __construct(
        protected AiSettingResolver $settings,
    ) {}

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        $fetched = $this->fetchFromApi();

        if ($fetched !== []) {
            return $fetched;
        }

        return config('ai.vision_models', []);
    }

    /**
     * @return array<string, string>
     */
    protected function fetchFromApi(): array
    {
        $apiKey = $this->settings->apiKey();

        if (blank($apiKey)) {
            return [];
        }

        return Cache::remember('openrouter.model_options', now()->addHours(6), function () use ($apiKey): array {
            try {
                $response = Http::baseUrl(rtrim((string) config('ai.openrouter.base_url'), '/'))
                    ->timeout(30)
                    ->withToken($apiKey)
                    ->get('/models');
            } catch (\Throwable) {
                return [];
            }

            if ($response->failed()) {
                return [];
            }

            $models = $response->json('data');

            if (! is_array($models)) {
                return [];
            }

            $options = [];

            foreach ($models as $model) {
                if (! is_array($model)) {
                    continue;
                }

                $id = (string) ($model['id'] ?? '');

                if ($id === '') {
                    continue;
                }

                if (! $this->supportsVisionInput($model)) {
                    continue;
                }

                $name = (string) ($model['name'] ?? $id);
                $options[$id] = $name . ' (' . $id . ')';
            }

            asort($options);

            return $options;
        });
    }

    /**
     * @param  array<string, mixed>  $model
     */
    protected function supportsVisionInput(array $model): bool
    {
        $architecture = $model['architecture'] ?? null;

        if (is_array($architecture)) {
            $inputModalities = $architecture['input_modalities'] ?? $architecture['input_modality'] ?? null;

            if (is_array($inputModalities)) {
                return in_array('image', $inputModalities, true) || in_array('file', $inputModalities, true);
            }

            if (is_string($inputModalities) && str_contains(strtolower($inputModalities), 'image')) {
                return true;
            }
        }

        $id = strtolower((string) ($model['id'] ?? ''));

        return str_contains($id, 'gemini')
            || str_contains($id, 'gpt-4o')
            || str_contains($id, 'claude')
            || str_contains($id, 'vision')
            || str_contains($id, 'llava');
    }

    public function resolveModel(?string $preferred = null): string
    {
        $preferred = trim((string) ($preferred ?? $this->settings->visionModel()));
        $options = $this->options();

        if ($preferred !== '' && array_key_exists($preferred, $options)) {
            return $preferred;
        }

        $fallback = (string) config('ai.openrouter.model', 'google/gemini-2.5-flash');

        if (array_key_exists($fallback, $options)) {
            return $fallback;
        }

        if ($options !== []) {
            return array_key_first($options);
        }

        return $fallback;
    }

    public function clearCache(): void
    {
        Cache::forget('openrouter.model_options');
    }
}
