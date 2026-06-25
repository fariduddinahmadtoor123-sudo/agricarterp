<?php

namespace App\Jobs;

use App\Models\Category;
use App\Services\Ai\CatalogEnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class EnrichCategoryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public int $categoryId,
    ) {}

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('enrich-category-' . $this->categoryId))->dontRelease(),
        ];
    }

    public function handle(CatalogEnrichmentService $service): void
    {
        try {
            $service->enrichCategory($this->categoryId);
        } catch (\Throwable $exception) {
            Log::warning('EnrichCategoryJob skipped after failure.', [
                'category_id' => $this->categoryId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function failed(?\Throwable $exception = null): void
    {
        Category::query()
            ->whereKey($this->categoryId)
            ->where('ai_status', Category::AI_STATUS_PROCESSING)
            ->update(['ai_status' => Category::AI_STATUS_FAILED]);
    }
}
