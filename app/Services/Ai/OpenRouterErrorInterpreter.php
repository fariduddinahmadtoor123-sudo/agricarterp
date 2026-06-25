<?php

namespace App\Services\Ai;

use App\Services\Ai\Exceptions\AiEnrichmentException;
use Illuminate\Http\Client\Response;
use Throwable;

class OpenRouterErrorInterpreter
{
    /**
     * @return array{
     *     error_code: int|null,
     *     raw_response: string|null,
     *     error_reason: string,
     *     suggested_action: string,
     *     message: string,
     * }
     */
    public function interpretHttpResponse(Response $response): array
    {
        $statusCode = $response->status();
        $rawBody = $response->body();
        $providerMessage = $response->json('error.message');

        if (! is_string($providerMessage) || blank($providerMessage)) {
            $providerMessage = is_string($rawBody) && strlen($rawBody) <= 500
                ? $rawBody
                : null;
        }

        return $this->interpretHttpStatus($statusCode, $providerMessage, $rawBody);
    }

    /**
     * @return array{
     *     error_code: int|null,
     *     raw_response: string|null,
     *     error_reason: string,
     *     suggested_action: string,
     *     message: string,
     * }
     */
    public function interpretHttpStatus(int $statusCode, ?string $providerMessage = null, ?string $rawBody = null): array
    {
        $reason = $this->reasonForStatus($statusCode, $providerMessage);
        $action = $this->actionForStatus($statusCode);

        return $this->buildDetails($statusCode, $rawBody, $reason, $action);
    }

    /**
     * @return array{
     *     error_code: int|null,
     *     raw_response: string|null,
     *     error_reason: string,
     *     suggested_action: string,
     *     message: string,
     * }
     */
    public function interpretException(Throwable $exception): array
    {
        if ($exception instanceof AiEnrichmentException && $exception->hasErrorDetails()) {
            $details = $exception->errorDetails();

            return $this->buildDetails(
                $details['error_code'] ?? null,
                $details['raw_response'] ?? null,
                $details['error_reason'] ?? $exception->getMessage(),
                $details['suggested_action'] ?? $this->genericAction(),
            );
        }

        $message = trim($exception->getMessage());

        return match (true) {
            str_contains(strtolower($message), 'could not reach openrouter') => $this->buildDetails(
                null,
                $message,
                'Could not connect to OpenRouter.',
                'Check your internet connection, firewall, and OpenRouter service status, then try again.',
            ),
            str_contains(strtolower($message), 'api key is missing') => $this->buildDetails(
                null,
                $message,
                'OpenRouter API key is not configured.',
                'Add your API key in Settings → AI Settings.',
            ),
            str_contains(strtolower($message), 'enrichment is disabled') => $this->buildDetails(
                null,
                $message,
                'AI enrichment is turned off.',
                'Enable enrichment in Settings → AI Settings.',
            ),
            str_contains(strtolower($message), 'empty response') => $this->buildDetails(
                null,
                $message,
                'OpenRouter returned an empty response.',
                'Try again. If it keeps failing, switch to a different vision model.',
            ),
            str_contains(strtolower($message), 'did not include any usable field values') => $this->buildDetails(
                null,
                $message,
                'The AI response did not contain usable field values.',
                'Retry enrichment. If it repeats, try another model or review the product image and source data.',
            ),
            default => $this->buildDetails(
                null,
                $message !== '' ? $message : null,
                $message !== '' ? $message : 'AI enrichment failed.',
                $this->genericAction(),
            ),
        };
    }

    protected function reasonForStatus(int $statusCode, ?string $providerMessage): string
    {
        $base = match ($statusCode) {
            401 => 'Invalid API key.',
            402 => 'OpenRouter credits exhausted.',
            403 => 'Access denied or model not allowed.',
            404 => 'Model not found.',
            408 => 'Request timeout.',
            429 => 'Rate limit exceeded. Too many requests.',
            500 => 'AI provider internal error.',
            503 => 'Service temporarily unavailable.',
            default => filled($providerMessage)
                ? trim((string) $providerMessage)
                : 'OpenRouter request failed.',
        };

        if ($statusCode >= 400 && filled($providerMessage) && ! str_contains($base, (string) $providerMessage)) {
            return $base . ' (' . trim((string) $providerMessage) . ')';
        }

        return $base;
    }

    protected function actionForStatus(int $statusCode): string
    {
        return match ($statusCode) {
            401 => 'Check the API key in Settings → AI Settings and create a new key on openrouter.ai if needed.',
            402 => 'Recharge your OpenRouter account or switch to a lower-cost vision model.',
            403 => 'Confirm your OpenRouter account can use this model, or choose another model in AI Settings.',
            404 => 'Choose a different vision model in Settings → AI Settings.',
            408 => 'Try again with a smaller batch size or wait and retry.',
            429 => 'Wait a few minutes, reduce records per run, and try again.',
            500 => 'Retry later. If it continues, switch models or check OpenRouter status.',
            503 => 'Retry later. OpenRouter or the model provider may be down briefly.',
            default => $this->genericAction(),
        };
    }

    protected function genericAction(): string
    {
        return 'Review Settings → AI Enrichment Logs for details, fix the issue, and run enrichment again.';
    }

    /**
     * @return array{
     *     error_code: int|null,
     *     raw_response: string|null,
     *     error_reason: string,
     *     suggested_action: string,
     *     message: string,
     * }
     */
    protected function buildDetails(
        ?int $errorCode,
        ?string $rawResponse,
        string $errorReason,
        string $suggestedAction,
    ): array {
        return [
            'error_code' => $errorCode,
            'raw_response' => $this->truncateRaw($rawResponse),
            'error_reason' => $errorReason,
            'suggested_action' => $suggestedAction,
            'message' => $this->summaryMessage($errorReason, $suggestedAction),
        ];
    }

    protected function summaryMessage(string $errorReason, string $suggestedAction): string
    {
        return 'Reason: ' . $errorReason . ' Suggested action: ' . $suggestedAction;
    }

    protected function truncateRaw(?string $rawResponse): ?string
    {
        if ($rawResponse === null || $rawResponse === '') {
            return null;
        }

        return mb_substr($rawResponse, 0, 10000);
    }
}
