<?php

namespace App\Services\Ai;

use App\Jobs\EnrichCategoryJob;
use App\Jobs\EnrichProductJob;
use App\Jobs\ProcessCatalogEnrichmentQueue;
use App\Models\Category;
use App\Models\Product;
use App\Services\Ai\Exceptions\AiEnrichmentException;
use App\Services\Settings\AiSettingResolver;
use Illuminate\Support\Collection;

class CatalogEnrichmentQueueService
{
    public function __construct(
        protected CatalogEnrichmentDetector $detector,
        protected AiSettingResolver $settings,
    ) {}

    /**
     * @return array{categories: int, products: int}
     */
    public function dispatchPending(bool $includeCategories = true, bool $includeProducts = true, ?int $limit = null): array
    {
        $this->assertReady();

        $limit ??= $this->settings->batchLimit();

        $categoryCount = 0;
        $productCount = 0;

        if ($includeCategories) {
            $categoryCount = $this->dispatchCategories($limit);
        }

        if ($includeProducts) {
            $remaining = max(0, $limit - $categoryCount);
            $productCount = $remaining > 0
                ? $this->dispatchProducts($remaining)
                : 0;
        }

        return [
            'categories' => $categoryCount,
            'products' => $productCount,
        ];
    }

    /**
     * Queue enrichment jobs, then process them automatically after the browser response.
     *
     * @return array{categories: int, products: int}
     */
    public function dispatchPendingAndProcess(bool $includeCategories = true, bool $includeProducts = true, ?int $limit = null): array
    {
        $queued = $this->dispatchPending($includeCategories, $includeProducts, $limit);

        if (($queued['categories'] + $queued['products']) > 0) {
            dispatch(new ProcessCatalogEnrichmentQueue())->afterResponse();
        }

        return $queued;
    }

    public function assertReady(): void
    {
        if (! $this->settings->isEnabled()) {
            throw new AiEnrichmentException('AI enrichment is disabled. Enable it in Settings → AI Settings.');
        }

        if (blank($this->settings->apiKey())) {
            throw new AiEnrichmentException('OpenRouter API key is missing. Add it in Settings → AI Settings.');
        }
    }

    protected function dispatchCategories(int $limit): int
    {
        return $this->dispatchCollection(
            $this->detector->pendingCategoriesQuery($limit)->get(),
            fn (Category $category): EnrichCategoryJob => new EnrichCategoryJob($category->id),
        );
    }

    protected function dispatchProducts(int $limit): int
    {
        return $this->dispatchCollection(
            $this->detector->pendingProductsQuery($limit)->get(),
            fn (Product $product): EnrichProductJob => new EnrichProductJob($product->id),
        );
    }

    /**
     * @template TModel of Category|Product
     *
     * @param  Collection<int, TModel>  $records
     * @param  callable(TModel): EnrichCategoryJob|EnrichProductJob  $jobFactory
     */
    protected function dispatchCollection(Collection $records, callable $jobFactory): int
    {
        $count = 0;

        foreach ($records as $record) {
            $needsEnrichment = $record instanceof Category
                ? $this->detector->categoryNeedsEnrichment($record)
                : $this->detector->productNeedsEnrichment($record);

            if (! $needsEnrichment) {
                continue;
            }

            dispatch($jobFactory($record));
            $count++;
        }

        return $count;
    }
}
