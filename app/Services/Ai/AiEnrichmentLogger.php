<?php

namespace App\Services\Ai;

use App\Models\AiEnrichmentLog;
use App\Models\Category;
use App\Models\Product;

class AiEnrichmentLogger
{
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
    protected function write(
        Category|Product $subject,
        string $status,
        string $model,
        string $message,
        array $context,
    ): void {
        AiEnrichmentLog::query()->create([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'subject_label' => $this->subjectLabel($subject),
            'status' => $status,
            'model' => $model,
            'message' => mb_substr($message, 0, 5000),
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
