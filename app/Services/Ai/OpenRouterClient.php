<?php

namespace App\Services\Ai;

use App\Services\Ai\Exceptions\AiEnrichmentException;
use App\Services\Settings\AiSettingResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenRouterClient
{
    public function __construct(
        protected AiSettingResolver $settings,
        protected OpenRouterModelCatalog $modelCatalog,
        protected OpenRouterErrorInterpreter $errorInterpreter,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    public function chat(array $messages): string
    {
        if (! $this->settings->isEnabled()) {
            throw new AiEnrichmentException('AI enrichment is disabled in settings.');
        }

        $apiKey = $this->settings->apiKey();

        if (blank($apiKey)) {
            throw new AiEnrichmentException('OpenRouter API key is missing. Add it in Settings → AI Settings.');
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('ai.openrouter.base_url'), '/'))
                ->timeout((int) config('ai.openrouter.timeout', 120))
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'HTTP-Referer' => (string) config('app.url'),
                    'X-Title' => (string) config('app.name'),
                ])
                ->post('/chat/completions', [
                    'model' => $this->modelCatalog->resolveModel($this->settings->visionModel()),
                    'max_tokens' => (int) config('ai.openrouter.max_tokens', 4096),
                    'messages' => $messages,
                    'response_format' => ['type' => 'json_object'],
                ]);
        } catch (ConnectionException $exception) {
            throw AiEnrichmentException::fromInterpreted(
                $this->errorInterpreter->interpretException($exception),
            );
        }

        if ($response->failed()) {
            throw AiEnrichmentException::fromInterpreted(
                $this->errorInterpreter->interpretHttpResponse($response),
            );
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || blank($content)) {
            throw new AiEnrichmentException('OpenRouter returned an empty response.');
        }

        return trim($content);
    }
}
