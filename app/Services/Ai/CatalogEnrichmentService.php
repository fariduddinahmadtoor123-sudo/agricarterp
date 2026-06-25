<?php

namespace App\Services\Ai;

use App\Models\Category;
use App\Models\Product;
use App\Services\Ai\Exceptions\AiEnrichmentException;
use Illuminate\Support\Facades\Log;

class CatalogEnrichmentService
{
    public function __construct(
        protected CatalogEnrichmentDetector $detector,
        protected CatalogEnrichmentPromptBuilder $promptBuilder,
        protected CatalogEnrichmentResponseParser $responseParser,
        protected CatalogImageEncoder $imageEncoder,
        protected OpenRouterClient $openRouter,
        protected AiEnrichmentLogger $enrichmentLogger,
        protected \App\Services\Settings\AiSettingResolver $aiSettings,
    ) {}

    public function enrichCategory(int $categoryId): void
    {
        $category = Category::query()->with('parent')->find($categoryId);

        if ($category === null) {
            return;
        }

        $this->enrichModel(
            model: $category,
            emptyFieldsResolver: fn (Category $record): array => $this->detector->emptyCategoryFields($record),
            needsEnrichmentResolver: fn (Category $record): bool => $this->detector->categoryNeedsEnrichment($record),
            promptResolver: fn (Category $record, array $emptyFields, ?string $image): array => $this->promptBuilder->buildCategoryMessages($record, $emptyFields, $image),
            imageResolver: fn (Category $record): ?string => $this->imageEncoder->encodeCategoryImage($record->image_path),
            configuredFields: config('ai.enrichment.category_fields', []),
            logContext: ['type' => 'category', 'id' => $categoryId],
        );
    }

    public function enrichProduct(int $productId): void
    {
        $product = Product::query()->with(['category', 'brand', 'images'])->find($productId);

        if ($product === null) {
            return;
        }

        $this->enrichModel(
            model: $product,
            emptyFieldsResolver: fn (Product $record): array => $this->detector->emptyProductFields($record),
            needsEnrichmentResolver: fn (Product $record): bool => $this->detector->productNeedsEnrichment($record),
            promptResolver: fn (Product $record, array $emptyFields, ?string $image): array => $this->promptBuilder->buildProductMessages($record, $emptyFields, $image),
            imageResolver: function (Product $record): ?string {
                $mainImage = $record->images->firstWhere('is_main', true) ?? $record->images->first();

                return $this->imageEncoder->encodeProductImage($mainImage?->image_path);
            },
            configuredFields: config('ai.enrichment.product_fields', []),
            logContext: ['type' => 'product', 'id' => $productId],
        );
    }

    /**
     * @template TModel of Category|Product
     *
     * @param  TModel  $model
     * @param  callable(TModel): list<string>  $emptyFieldsResolver
     * @param  callable(TModel): bool  $needsEnrichmentResolver
     * @param  callable(TModel, list<string>, ?string): array<int, array<string, mixed>>  $promptResolver
     * @param  callable(TModel): ?string  $imageResolver
     * @param  list<string>  $configuredFields
     * @param  array<string, mixed>  $logContext
     */
    protected function enrichModel(
        object $model,
        callable $emptyFieldsResolver,
        callable $needsEnrichmentResolver,
        callable $promptResolver,
        callable $imageResolver,
        array $configuredFields,
        array $logContext,
    ): void {
        if (! app(\App\Services\Settings\AiSettingResolver::class)->isEnabled()) {
            return;
        }

        if (! $needsEnrichmentResolver($model)) {
            $this->markCompleteIfFilled($model, $configuredFields);

            return;
        }

        $emptyFields = $emptyFieldsResolver($model);

        if ($emptyFields === []) {
            $this->markComplete($model);

            return;
        }

        $model->forceFill(['ai_status' => $this->processingStatus($model)])->save();

        $modelName = $this->aiSettings->resolvedVisionModel();

        try {
            $imageDataUri = $imageResolver($model);
            $messages = $promptResolver($model, $emptyFields, $imageDataUri);
            $rawResponse = $this->openRouter->chat($messages);
            $parsed = $this->responseParser->parse($rawResponse);
            $updates = $this->extractAllowedUpdates($model, $parsed, $emptyFields);

            if ($updates === []) {
                throw new AiEnrichmentException('AI response did not include any usable field values.');
            }

            $model->forceFill($updates);
            $model->forceFill([
                'ai_generated_at' => now(),
                'ai_version' => (string) config('ai.version', '1'),
            ]);

            $remainingEmpty = $this->remainingEmptyFields($model, $configuredFields);
            $finalStatus = $remainingEmpty === []
                ? $this->completeStatus($model)
                : $this->reviewStatus($model);

            $model->forceFill(['ai_status' => $finalStatus]);
            $model->save();

            $this->enrichmentLogger->logSuccess($model, $modelName, [
                'filled_fields' => array_keys($updates),
                'final_status' => $finalStatus,
            ]);
        } catch (\Throwable $exception) {
            $model->forceFill(['ai_status' => $this->failedStatus($model)])->save();

            $this->enrichmentLogger->logFailureFromException($model, $modelName, $exception, $logContext);

            Log::error('Catalog AI enrichment failed.', [
                ...$logContext,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  list<string>  $emptyFields
     * @return array<string, mixed>
     */
    protected function extractAllowedUpdates(object $model, array $parsed, array $emptyFields): array
    {
        $updates = [];

        foreach ($emptyFields as $field) {
            if (! array_key_exists($field, $parsed)) {
                continue;
            }

            $value = $parsed[$field];

            if (! $this->valueIsUsable($value)) {
                continue;
            }

            if ($this->fieldHasValue($model->{$field} ?? null)) {
                continue;
            }

            $updates[$field] = is_string($value) ? trim($value) : $value;
        }

        return $updates;
    }

    /**
     * @param  list<string>  $configuredFields
     * @return list<string>
     */
    protected function remainingEmptyFields(object $model, array $configuredFields): array
    {
        $remaining = [];

        foreach ($configuredFields as $field) {
            if (! $this->fieldHasValue($model->{$field} ?? null)) {
                $remaining[] = $field;
            }
        }

        return $remaining;
    }

    /**
     * @param  list<string>  $configuredFields
     */
    protected function markCompleteIfFilled(object $model, array $configuredFields): void
    {
        if ($this->remainingEmptyFields($model, $configuredFields) === []) {
            $this->markComplete($model);
        }
    }

    protected function markComplete(object $model): void
    {
        $model->forceFill(['ai_status' => $this->completeStatus($model)])->save();
    }

    protected function processingStatus(object $model): string
    {
        return $model instanceof Category
            ? Category::AI_STATUS_PROCESSING
            : Product::AI_STATUS_PROCESSING;
    }

    protected function completeStatus(object $model): string
    {
        return $model instanceof Category
            ? Category::AI_STATUS_COMPLETE
            : Product::AI_STATUS_COMPLETE;
    }

    protected function reviewStatus(object $model): string
    {
        return $model instanceof Category
            ? Category::AI_STATUS_REVIEW
            : Product::AI_STATUS_REVIEW;
    }

    protected function failedStatus(object $model): string
    {
        return $model instanceof Category
            ? Category::AI_STATUS_FAILED
            : Product::AI_STATUS_FAILED;
    }

    protected function valueIsUsable(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    protected function fieldHasValue(mixed $value): bool
    {
        return $this->valueIsUsable($value);
    }
}
