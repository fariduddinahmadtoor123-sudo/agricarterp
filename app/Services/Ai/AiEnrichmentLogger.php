<?php

namespace App\Services\Ai;

use App\Models\AiEnrichmentLog;
use App\Models\Category;
use App\Models\Product;
use Throwable;

class AiEnrichmentLogger
{
    public function __construct(
        protected OpenRouterErrorInterpreter $errorInterpreter,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function logSuccess(Category|Product $subject, string $model, array $context = []): void
    {
        $this->write($subject, AiEnrichmentLog::STATUS_SUCCESS, $model, 'Enrichment completed.', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logFailure(Category|Product $subject, string $model, string $message, array $context = []): void
    {
        $this->write($subject, AiEnrichmentLog::STATUS_FAILED, $model, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logFailureFromException(
        Category|Product $subject,
        string $model,
        Throwable $exception,
        array $context = [],
    ): void {
        $details = $this->errorInterpreter->interpretException($exception);

        $this->write(
            subject: $subject,
            status: AiEnrichmentLog::STATUS_FAILED,
            model: $model,
            message: (string) ($details['message'] ?? $exception->getMessage()),
            context: $context,
            errorDetails: $details,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array{
     *     error_code?: int|null,
     *     raw_response?: string|null,
     *     error_reason?: string,
     *     suggested_action?: string,
     *     message?: string,
     * }|null  $errorDetails
     */
    protected function write(
        Category|Product $subject,
        string $status,
        string $model,
        string $message,
        array $context,
        ?array $errorDetails = null,
    ): void {
        AiEnrichmentLog::query()->create([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'subject_label' => $this->subjectLabel($subject),
            'status' => $status,
            'model' => $model,
            'message' => mb_substr($message, 0, 5000),
            'error_code' => $errorDetails['error_code'] ?? null,
            'error_reason' => isset($errorDetails['error_reason'])
                ? mb_substr((string) $errorDetails['error_reason'], 0, 5000)
                : null,
            'suggested_action' => isset($errorDetails['suggested_action'])
                ? mb_substr((string) $errorDetails['suggested_action'], 0, 5000)
                : null,
            'raw_response' => isset($errorDetails['raw_response'])
                ? mb_substr((string) $errorDetails['raw_response'], 0, 10000)
                : null,
            'context' => $context === [] ? null : $context,
        ]);
    }

    protected function subjectLabel(Category|Product $subject): string
    {
        if ($subject instanceof Product) {
            return trim($subject->product_number . ' — ' . $subject->name_en);
        }

        return trim((string) ($subject->category_number ?? '') . ' — ' . $subject->name_en);
    }
}
