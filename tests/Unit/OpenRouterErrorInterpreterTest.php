<?php

namespace Tests\Unit;

use App\Models\AiEnrichmentLog;
use App\Services\Ai\AiEnrichmentLogger;
use App\Services\Ai\Exceptions\AiEnrichmentException;
use App\Services\Ai\OpenRouterErrorInterpreter;
use App\Services\ProductCatalog\CategoryPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterErrorInterpreterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_maps_common_http_status_codes_to_friendly_messages(): void
    {
        $interpreter = app(OpenRouterErrorInterpreter::class);

        $cases = [
            401 => ['Invalid API key.', 'Check the API key'],
            402 => ['OpenRouter credits exhausted.', 'Recharge your OpenRouter account'],
            403 => ['Access denied or model not allowed.', 'Confirm your OpenRouter account'],
            404 => ['Model not found.', 'Choose a different vision model'],
            408 => ['Request timeout.', 'Try again with a smaller batch size'],
            429 => ['Rate limit exceeded. Too many requests.', 'Wait a few minutes'],
            500 => ['AI provider internal error.', 'Retry later'],
            503 => ['Service temporarily unavailable.', 'Retry later'],
        ];

        foreach ($cases as $status => [$reasonFragment, $actionFragment]) {
            $details = $interpreter->interpretHttpStatus($status, 'provider detail');

            $this->assertSame($status, $details['error_code']);
            $this->assertStringContainsString($reasonFragment, $details['error_reason']);
            $this->assertStringContainsString($actionFragment, $details['suggested_action']);
            $this->assertStringContainsString('Reason:', $details['message']);
            $this->assertStringContainsString('Suggested action:', $details['message']);
        }
    }

    public function test_it_stores_raw_response_from_failed_http_response(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/*' => Http::response([
                'error' => ['message' => 'Insufficient credits'],
            ], 402),
        ]);

        $response = Http::baseUrl('https://openrouter.ai/api/v1')->post('/chat/completions', []);
        $details = app(OpenRouterErrorInterpreter::class)->interpretHttpResponse($response);

        $this->assertSame(402, $details['error_code']);
        $this->assertStringContainsString('Insufficient credits', (string) $details['raw_response']);
    }

    public function test_logger_persists_structured_failure_details(): void
    {
        $category = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Spray Pump',
            'name_ur' => '',
        ]);

        $exception = AiEnrichmentException::fromInterpreted(
            app(OpenRouterErrorInterpreter::class)->interpretHttpStatus(402, 'Insufficient credits', '{"error":{"message":"Insufficient credits"}}'),
        );

        app(AiEnrichmentLogger::class)->logFailureFromException(
            $category,
            'google/gemini-2.5-flash',
            $exception,
            ['type' => 'category', 'id' => $category->id],
        );

        $log = AiEnrichmentLog::query()->first();

        $this->assertNotNull($log);
        $this->assertSame(AiEnrichmentLog::STATUS_FAILED, $log->status);
        $this->assertSame(402, $log->error_code);
        $this->assertStringContainsString('credits exhausted', strtolower((string) $log->error_reason));
        $this->assertStringContainsString('Recharge', (string) $log->suggested_action);
        $this->assertStringContainsString('Insufficient credits', (string) $log->raw_response);
        $this->assertStringContainsString('Reason:', $log->adminSummary());
    }
}
