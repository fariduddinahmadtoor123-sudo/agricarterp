<?php

namespace App\Console\Commands;

use App\Services\Ai\CatalogEnrichmentQueueService;
use App\Services\Settings\AiSettingResolver;
use Illuminate\Console\Command;

class CatalogEnrichPendingCommand extends Command
{
    protected $signature = 'catalog:enrich-pending
                            {--categories : Queue only categories}
                            {--products : Queue only products}
                            {--limit= : Maximum records to queue in this run}
                            {--dry-run : Show how many records would be queued without dispatching jobs}
                            {--process : Process queued jobs immediately after dispatching}';

    protected $description = 'Queue background AI jobs for categories and products with empty fields';

    public function handle(CatalogEnrichmentQueueService $queueService, AiSettingResolver $settings): int
    {
        try {
            $queueService->assertReady();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $onlyCategories = (bool) $this->option('categories');
        $onlyProducts = (bool) $this->option('products');
        $includeCategories = ! $onlyProducts || $onlyCategories;
        $includeProducts = ! $onlyCategories || $onlyProducts;

        if ($onlyCategories && $onlyProducts) {
            $includeCategories = true;
            $includeProducts = true;
        }

        $limit = $this->option('limit');
        $limit = filled($limit) ? max(1, (int) $limit) : $settings->batchLimit();

        if ($this->option('dry-run')) {
            $categoryCount = $includeCategories
                ? app(\App\Services\Ai\CatalogEnrichmentDetector::class)->pendingCategoriesQuery($limit)->count()
                : 0;
            $productCount = $includeProducts
                ? app(\App\Services\Ai\CatalogEnrichmentDetector::class)->pendingProductsQuery($limit)->count()
                : 0;

            $this->info("Dry run: {$categoryCount} categories and {$productCount} products match.");
            $this->line('Run without --dry-run to queue background jobs.');

            return self::SUCCESS;
        }

        $queued = $this->option('process')
            ? $queueService->dispatchPendingAndProcess($includeCategories, $includeProducts, $limit)
            : $queueService->dispatchPending($includeCategories, $includeProducts, $limit);

        $this->info("Queued {$queued['categories']} categories and {$queued['products']} products.");

        if (! $this->option('process')) {
            $this->line('Make sure a queue worker is running: php artisan queue:work');
        }

        return self::SUCCESS;
    }
}
