<?php

namespace App\Filament\Pages\PurchasingInventory;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Services\PurchasingInventory\PurchasePlanningCategorySearch;
use App\Services\PurchasingInventory\ReOrderCandidateService;
use App\Support\PurchasingInventory\ReOrderQueueRepository;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ReOrderCenter extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'purchasing-inventory/re-order-center';

    protected static bool $shouldRegisterNavigation = false;

    public string $stockFilter = 'all';

    public string $queueFilter = 'all';

    public string $nameLang = 'both';

    public string $purchaserName = '';

    public ?int $loadCategoryId = null;

    public string $categorySearch = '';

    /** @var list<array<string, mixed>> */
    public array $categorySearchResults = [];

    public string $selectedCategoryLabel = '';

    /** @var list<int> */
    public array $selectedProductIds = [];

    public static function moduleKey(): string
    {
        return 'purchasing-inventory';
    }

    public static function submenuKey(): string
    {
        return 're-order-center';
    }

    public function mount(): void
    {
        app(ReOrderQueueRepository::class)->flagStaleOrders();
    }

    public function getTitle(): string | Htmlable
    {
        return 'Re-Order Center';
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.purchasing-inventory.re-order-center')
                ->viewData(fn (): array => $this->viewData()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        $candidates = app(ReOrderCandidateService::class)->candidates(
            $this->stockFilter,
            $this->loadCategoryId,
        );

        $queueRows = app(ReOrderQueueRepository::class)->queueRows($this->queueFilter);

        return [
            'candidates' => $candidates,
            'queueRows' => $queueRows,
            'candidateCount' => count($candidates),
            'queueCount' => count($queueRows),
            'stockFilters' => config('purchasing-inventory.reorder_stock_filters', []),
            'queueFilters' => config('purchasing-inventory.reorder_queue_filters', []),
            'queueStatuses' => config('purchasing-inventory.reorder_queue_statuses', []),
            'staleDays' => (int) config('purchasing-inventory.reorder_stale_days', 7),
            'categorySearch' => $this->categorySearch,
            'categorySearchResults' => $this->categorySearchResults,
            'selectedCategoryLabel' => $this->selectedCategoryLabel,
            'nameLang' => $this->nameLang,
            'stockFilter' => $this->stockFilter,
            'queueFilter' => $this->queueFilter,
            'purchaserName' => $this->purchaserName,
            'selectedProductIds' => $this->selectedProductIds,
        ];
    }

    public function updatedStockFilter(): void
    {
        $this->selectedProductIds = [];
    }

    public function updatedQueueFilter(): void
    {
        //
    }

    public function updatedCategorySearch(): void
    {
        $this->refreshCategorySearch();
    }

    public function focusCategorySearch(): void
    {
        if ($this->loadCategoryId !== null && $this->categorySearch === $this->selectedCategoryLabel) {
            $this->categorySearch = '';
            $this->loadCategoryId = null;
            $this->selectedCategoryLabel = '';
            $this->categorySearchResults = [];
        }
    }

    public function refreshCategorySearch(): void
    {
        $term = trim($this->categorySearch);

        if ($this->loadCategoryId !== null && $term !== $this->selectedCategoryLabel) {
            $this->loadCategoryId = null;
            $this->selectedCategoryLabel = '';
        }

        if ($term === '') {
            $this->categorySearchResults = [];
            $this->loadCategoryId = null;
            $this->selectedCategoryLabel = '';

            return;
        }

        if ($this->loadCategoryId !== null && $term === $this->selectedCategoryLabel) {
            $this->categorySearchResults = [];

            return;
        }

        $this->categorySearchResults = app(PurchasePlanningCategorySearch::class)->search($term);
    }

    public function selectCategoryFilter(int $categoryId): void
    {
        $label = app(PurchasePlanningCategorySearch::class)->labelForId($categoryId);

        if ($label === null) {
            return;
        }

        $this->loadCategoryId = $categoryId;
        $this->selectedCategoryLabel = $label;
        $this->categorySearch = $label;
        $this->categorySearchResults = [];
        $this->selectedProductIds = [];
    }

    public function clearCategoryFilter(): void
    {
        $this->loadCategoryId = null;
        $this->selectedCategoryLabel = '';
        $this->categorySearch = '';
        $this->categorySearchResults = [];
        $this->selectedProductIds = [];
    }

    public function setStockFilter(string $filter): void
    {
        if (! array_key_exists($filter, config('purchasing-inventory.reorder_stock_filters', []))) {
            return;
        }

        $this->stockFilter = $filter;
        $this->selectedProductIds = [];
    }

    public function setQueueFilter(string $filter): void
    {
        if (! array_key_exists($filter, config('purchasing-inventory.reorder_queue_filters', []))) {
            return;
        }

        $this->queueFilter = $filter;
    }

    public function setNameLang(string $lang): void
    {
        if (! in_array($lang, ['both', 'en', 'ur'], true)) {
            return;
        }

        $this->nameLang = $lang;
    }

    public function toggleProductSelection(int $productId): void
    {
        if (in_array($productId, $this->selectedProductIds, true)) {
            $this->selectedProductIds = array_values(array_filter(
                $this->selectedProductIds,
                fn (int $id): bool => $id !== $productId,
            ));

            return;
        }

        $this->selectedProductIds[] = $productId;
    }

    public function selectAllCandidates(): void
    {
        $candidates = app(ReOrderCandidateService::class)->candidates(
            $this->stockFilter,
            $this->loadCategoryId,
        );

        $this->selectedProductIds = collect($candidates)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function clearCandidateSelection(): void
    {
        $this->selectedProductIds = [];
    }

    public function sendSelectedOrder(): void
    {
        $this->sendOrder($this->selectedProductIds);
    }

    public function sendAllCandidates(): void
    {
        $candidates = app(ReOrderCandidateService::class)->candidates(
            $this->stockFilter,
            $this->loadCategoryId,
        );

        $this->sendOrder(
            collect($candidates)->pluck('product_id')->map(fn ($id): int => (int) $id)->all(),
        );
    }

    /**
     * @param  list<int>  $productIds
     */
    protected function sendOrder(array $productIds): void
    {
        $productIds = array_values(array_unique(array_filter($productIds)));

        if ($productIds === []) {
            Notification::make()
                ->warning()
                ->title('Select at least one product')
                ->send();

            return;
        }

        if (trim($this->purchaserName) === '') {
            Notification::make()
                ->warning()
                ->title('Enter purchaser name')
                ->body('Mention who will receive this re-order list.')
                ->send();

            return;
        }

        $lines = app(ReOrderCandidateService::class)->linesForProductIds($productIds);

        if ($lines === []) {
            Notification::make()
                ->warning()
                ->title('No valid products to send')
                ->send();

            return;
        }

        $order = app(ReOrderQueueRepository::class)->createOrder(
            $this->purchaserName,
            $this->nameLang,
            $lines,
        );

        $this->selectedProductIds = [];

        Notification::make()
            ->success()
            ->title('Re-order sent')
            ->body((string) $order['order_number'] . ' sent to ' . $this->purchaserName)
            ->send();

        $this->redirect(ReOrderSendWorksheet::getUrl(['orderId' => $order['id']]));
    }

    public function markOrderReceived(string $orderId): void
    {
        if (! app(ReOrderQueueRepository::class)->markReceived($orderId)) {
            Notification::make()
                ->warning()
                ->title('Order not found')
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Marked as received')
            ->body('Order marked as received and removed from active queue.')
            ->send();
    }

    public function markOrderDisputed(string $orderId): void
    {
        if (! app(ReOrderQueueRepository::class)->markDisputed($orderId)) {
            Notification::make()
                ->warning()
                ->title('Order not found')
                ->send();

            return;
        }

        Notification::make()
            ->info()
            ->title('Moved to disputed')
            ->send();
    }

    public function reopenOrder(string $orderId): void
    {
        if (! app(ReOrderQueueRepository::class)->markPending($orderId)) {
            Notification::make()
                ->warning()
                ->title('Order not found')
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Order moved back to pending')
            ->send();
    }
}
