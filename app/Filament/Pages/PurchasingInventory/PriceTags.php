<?php

namespace App\Filament\Pages\PurchasingInventory;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Services\PurchasingInventory\PriceTagImportService;
use App\Services\PurchasingInventory\PriceTagPresenter;
use App\Services\PurchasingInventory\PurchasePlanningProductSearch;
use App\Support\PurchasingInventory\PriceTagQueueRepository;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class PriceTags extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'purchasing-inventory/price-tags';

    protected static bool $shouldRegisterNavigation = false;

    public string $productSearch = '';

    /** @var list<array<string, mixed>> */
    public array $searchResults = [];

    public string $purchaseInvoiceNumber = '';

    /** @var list<array<string, mixed>> */
    public array $queueLines = [];

    public string $scanMode = 'barcode';

    /** @var array<string, bool> */
    public array $printFields = [];

    public static function moduleKey(): string
    {
        return 'purchasing-inventory';
    }

    public static function submenuKey(): string
    {
        return 'price-tags';
    }

    public function mount(): void
    {
        $this->hydrateFromSession();
    }

    public function getTitle(): string | Htmlable
    {
        return 'Price Tags';
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.purchasing-inventory.price-tags')
                ->viewData(fn (): array => $this->viewData()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        $repository = app(PriceTagQueueRepository::class);
        $presenter = app(PriceTagPresenter::class);
        $settings = $this->currentSettings();

        $previewLines = collect($this->queueLines)
            ->map(function (array $line) use ($presenter, $settings): array {
                return array_merge($line, [
                    'sticker' => $presenter->stickerData($line, $settings),
                ]);
            })
            ->all();

        return [
            'queueLines' => $previewLines,
            'stickerCount' => $repository->stickerCount(),
            'activeLineCount' => $repository->activeLineCount(),
            'scanModes' => config('purchasing-inventory.price_tag_scan_modes', []),
            'printFieldLabels' => config('purchasing-inventory.price_tag_print_fields', []),
            'printFields' => $this->printFields,
            'scanMode' => $this->scanMode,
            'purchaseInvoiceOptions' => app(PriceTagImportService::class)->purchaseInvoiceOptions(),
            'searchResults' => $this->searchResults,
        ];
    }

    public function updatedProductSearch(): void
    {
        $term = trim($this->productSearch);

        if (mb_strlen($term) < 2) {
            $this->searchResults = [];

            return;
        }

        $this->searchResults = app(PurchasePlanningProductSearch::class)->search($term, 12);
    }

    public function addProductFromSearch(): void
    {
        $term = trim($this->productSearch);

        if ($term === '') {
            return;
        }

        $search = app(PurchasePlanningProductSearch::class);
        $product = $search->findExactMatch($term)
            ?? ($this->searchResults[0] ?? null)
            ?? $search->search($term, 1)[0] ?? null;

        if ($product === null) {
            Notification::make()
                ->warning()
                ->title('Product not found')
                ->send();

            return;
        }

        $this->addProductToQueue($product);
        $this->productSearch = '';
        $this->searchResults = [];
    }

    public function selectSearchResult(int $productId): void
    {
        $product = app(PurchasePlanningProductSearch::class)->findById($productId);

        if ($product === null) {
            return;
        }

        $this->addProductToQueue($product);
        $this->productSearch = '';
        $this->searchResults = [];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function addProductToQueue(array $product): void
    {
        $productId = (int) ($product['id'] ?? 0);

        foreach ($this->queueLines as $line) {
            if ((int) ($line['product_id'] ?? 0) === $productId && ! (bool) ($line['disabled'] ?? false)) {
                Notification::make()
                    ->info()
                    ->title('Product already in sticker queue')
                    ->send();

                return;
            }
        }

        $line = app(\App\Services\PurchasingInventory\PriceTagLineBuilder::class)->fromCatalogProduct($product);
        $this->queueLines[] = $line;
        $this->persistQueue();

        Notification::make()
            ->success()
            ->title($line['name_en'] . ' added to sticker queue')
            ->send();
    }

    public function loadPurchaseInvoice(): void
    {
        $this->importPurchaseInvoice(false);
    }

    public function mergePurchaseInvoice(): void
    {
        $this->importPurchaseInvoice(true);
    }

    protected function importPurchaseInvoice(bool $merge): void
    {
        $number = trim($this->purchaseInvoiceNumber);

        if ($number === '') {
            Notification::make()
                ->warning()
                ->title('Select or enter a purchase invoice number')
                ->send();

            return;
        }

        $import = app(PriceTagImportService::class)->linesFromPurchaseNumber($number);

        if ($import === null || $import['lines'] === []) {
            Notification::make()
                ->warning()
                ->title('Purchase invoice not found or has no product lines')
                ->send();

            return;
        }

        if (! $merge) {
            $this->queueLines = $import['lines'];
        } else {
            $existingIds = collect($this->queueLines)->pluck('product_id')->all();

            foreach ($import['lines'] as $line) {
                if (in_array((int) ($line['product_id'] ?? 0), $existingIds, true)) {
                    continue;
                }

                $this->queueLines[] = $line;
                $existingIds[] = (int) $line['product_id'];
            }
        }

        $this->persistQueue();

        Notification::make()
            ->success()
            ->title($merge ? 'Invoice merged into queue' : 'Invoice loaded into queue')
            ->body($import['purchase_number'] . ' — ' . count($import['lines']) . ' products')
            ->send();
    }

    public function clearQueue(): void
    {
        $this->queueLines = [];
        app(PriceTagQueueRepository::class)->clear();

        Notification::make()
            ->success()
            ->title('Sticker queue cleared')
            ->send();
    }

    public function incrementPrintQty(string $lineId): void
    {
        $this->updateLine($lineId, function (array &$line): void {
            $line['print_qty'] = max(1, (int) ($line['print_qty'] ?? 1) + 1);
        });
    }

    public function decrementPrintQty(string $lineId): void
    {
        $this->updateLine($lineId, function (array &$line): void {
            $line['print_qty'] = max(1, (int) ($line['print_qty'] ?? 1) - 1);
        });
    }

    public function updatedQueueLines(): void
    {
        $this->normalizeQueueLines();
        $this->persistQueue();
    }

    public function toggleLineDisabled(string $lineId): void
    {
        $this->updateLine($lineId, function (array &$line): void {
            $line['disabled'] = ! (bool) ($line['disabled'] ?? false);
        });
    }

    public function removeLine(string $lineId): void
    {
        $this->queueLines = array_values(array_filter(
            $this->queueLines,
            fn (array $line): bool => ($line['line_id'] ?? '') !== $lineId,
        ));

        $this->persistQueue();
    }

    public function setScanMode(string $mode): void
    {
        if (! array_key_exists($mode, config('purchasing-inventory.price_tag_scan_modes', []))) {
            return;
        }

        $this->scanMode = $mode;
        $this->persistSettings();
    }

    public function togglePrintField(string $field): void
    {
        if (! array_key_exists($field, config('purchasing-inventory.price_tag_print_fields', []))) {
            return;
        }

        $this->printFields[$field] = ! (bool) ($this->printFields[$field] ?? false);
        $this->persistSettings();
    }

    public function printTags(): void
    {
        if ($this->queueLines === [] || app(PriceTagQueueRepository::class)->stickerCount() === 0) {
            Notification::make()
                ->warning()
                ->title('Add products to the sticker queue first')
                ->send();

            return;
        }

        $this->dispatch('agricart-pt-print');
    }

    protected function updateLine(string $lineId, callable $callback): void
    {
        foreach ($this->queueLines as $index => $line) {
            if (($line['line_id'] ?? '') !== $lineId) {
                continue;
            }

            $callback($this->queueLines[$index]);
            break;
        }

        $this->persistQueue();
    }

    protected function hydrateFromSession(): void
    {
        $repository = app(PriceTagQueueRepository::class);
        $settings = $repository->settings();

        $this->queueLines = $repository->lines();
        $this->scanMode = (string) ($settings['scan_mode'] ?? 'barcode');
        $this->printFields = is_array($settings['fields'] ?? null) ? $settings['fields'] : [];
    }

    protected function persistQueue(): void
    {
        $this->normalizeQueueLines();
        app(PriceTagQueueRepository::class)->persistLines($this->queueLines);
    }

    protected function persistSettings(): void
    {
        app(PriceTagQueueRepository::class)->persistSettings($this->currentSettings());
    }

    /**
     * @return array<string, mixed>
     */
    protected function currentSettings(): array
    {
        return [
            'scan_mode' => $this->scanMode,
            'fields' => $this->printFields,
        ];
    }

    protected function normalizeQueueLines(): void
    {
        foreach ($this->queueLines as $index => $line) {
            $qty = (int) ($line['print_qty'] ?? 1);
            $this->queueLines[$index]['print_qty'] = max(1, $qty);
        }
    }
}
