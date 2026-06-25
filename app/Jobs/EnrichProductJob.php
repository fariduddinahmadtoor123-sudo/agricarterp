<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Ai\CatalogEnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class EnrichProductJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public int $productId,
    ) {}

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('enrich-product-' . $this->productId))->dontRelease(),
        ];
    }

    public function handle(CatalogEnrichmentService $service): void
    {
        try {
            $service->enrichProduct($this->productId);
        } catch (\Throwable $exception) {
            Log::warning('EnrichProductJob skipped after failure.', [
                'product_id' => $this->productId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function failed(?\Throwable $exception = null): void
    {
        Product::query()
            ->whereKey($this->productId)
            ->where('ai_status', Product::AI_STATUS_PROCESSING)
            ->update(['ai_status' => Product::AI_STATUS_FAILED]);
    }
}
