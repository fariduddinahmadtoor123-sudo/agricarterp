<?php

namespace App\Filament\Pages\PurchasingInventory;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Models\Supplier;
use App\Services\PurchasingInventory\PurchasePlanningBulkLoad;
use App\Services\PurchasingInventory\PurchasePlanningCategorySearch;
use App\Services\PurchasingInventory\PurchasePlanningProductSearch;
use App\Services\PurchasingInventory\PurchaseQuotationLineBuilder;
use App\Support\PurchasingInventory\PurchaseQuotationSheetRepository;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class PurchaseQuotationWorksheet extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'purchasing-inventory/purchase-quotations/{quotationId}';

    protected static bool $shouldRegisterNavigation = false;

    public string $quotationId = '';

    /** @var array<string, mixed> */
    public array $sheet = [];

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public string $productSearch = '';

    /** @var list<array<string, mixed>> */
    public array $searchResults = [];

    public string $nameLang = 'both';

    public string $sheetTitle = '';

    public string $sheetDate = '';

    public string $notes = '';

    public ?int $supplierId = null;

    public string $storeKey = '';

    public ?int $loadCategoryId = null;

    public string $categorySearch = '';

    /** @var list<array<string, mixed>> */
    public array $categorySearchResults = [];

    public string $selectedCategoryLabel = '';

    public static function moduleKey(): string
    {
        return 'purchasing-inventory';
    }

    public static function submenuKey(): string
    {
        return 'purchase-quotations';
    }

    public function mount(string $quotationId): void
    {
        $sheet = app(PurchaseQuotationSheetRepository::class)->find($quotationId);

        abort_if($sheet === null, 404);

        $this->quotationId = $quotationId;
        $this->hydrateFromSheet($sheet);
    }

    public function getTitle(): string | Htmlable
    {
        return (string) ($this->sheet['quotation_number'] ?? 'Purchase Quotation');
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.purchasing-inventory.purchase-quotation-worksheet')
                ->viewData(fn (): array => [
                    'itemCount' => count($this->rows),
                    'quotationNumber' => (string) ($this->sheet['quotation_number'] ?? ''),
                    'sheet' => $this->sheet,
                    'isNewQuotation' => ($this->sheet['status'] ?? 'draft') === 'draft' && $this->rows === [],
                    'sheetTitle' => $this->sheetTitle,
                    'nameLang' => $this->nameLang,
                    'searchResults' => $this->searchResults,
                    'categorySearch' => $this->categorySearch,
                    'categorySearchResults' => $this->categorySearchResults,
                    'selectedCategoryLabel' => $this->selectedCategoryLabel,
                    'rows' => $this->rows,
                    'minVisualRows' => (int) config('purchasing-inventory.worksheet_min_visual_rows', 10),
                    'supplierOptions' => $this->supplierOptions(),
                    'supplierId' => $this->supplierId,
                    'storeOptions' => config('purchasing-inventory.demo_stores', []),
                    'storeKey' => $this->storeKey,
                    'storeName' => (string) ($this->sheet['store_name'] ?? ''),
                    'grandTotal' => PurchaseQuotationLineBuilder::grandTotal($this->rows),
                ]),
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function supplierOptions(): array
    {
        return Supplier::operational()
            ->orderBy('business_name')
            ->pluck('business_name', 'id')
            ->all();
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

            return;
        }

        if ($this->loadCategoryId !== null && $term === $this->selectedCategoryLabel) {
            $this->categorySearchResults = [];

            return;
        }

        $this->categorySearchResults = app(PurchasePlanningCategorySearch::class)->search($term);
    }

    public function selectCategoryForLoad(int $categoryId): void
    {
        $label = app(PurchasePlanningCategorySearch::class)->labelForId($categoryId);

        if ($label === null) {
            return;
        }

        $this->loadCategoryId = $categoryId;
        $this->selectedCategoryLabel = $label;
        $this->categorySearch = $label;
        $this->categorySearchResults = [];
    }

    public function clearCategorySelection(): void
    {
        $this->loadCategoryId = null;
        $this->selectedCategoryLabel = '';
        $this->categorySearch = '';
        $this->categorySearchResults = [];
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
                ->body('Scan barcode or search by SKU, English or Urdu name.')
                ->send();

            return;
        }

        $this->addProduct($product);
        $this->productSearch = '';
        $this->searchResults = [];
    }

    public function selectSearchResult(int $productId): void
    {
        $product = app(PurchasePlanningProductSearch::class)->findById($productId);

        if ($product === null) {
            return;
        }

        $this->addProduct($product);
        $this->productSearch = '';
        $this->searchResults = [];
    }

    public function setNameLang(string $lang): void
    {
        if (! in_array($lang, ['both', 'en', 'ur'], true)) {
            return;
        }

        $this->nameLang = $lang;
        $this->sheet['name_lang'] = $lang;
        $this->persistSheet(false);
    }

    public function updatedSupplierId(): void
    {
        if ($this->supplierId === null) {
            $this->sheet['supplier_id'] = null;
            $this->sheet['supplier_name'] = '';
            $this->persistSheet(false);

            return;
        }

        $name = $this->supplierOptions()[$this->supplierId] ?? null;

        if ($name === null) {
            $this->supplierId = null;
            $this->sheet['supplier_id'] = null;
            $this->sheet['supplier_name'] = '';
            $this->persistSheet(false);

            return;
        }

        $this->sheet['supplier_id'] = $this->supplierId;
        $this->sheet['supplier_name'] = $name;
        $this->persistSheet(false);
    }

    public function updatedStoreKey(): void
    {
        $stores = config('purchasing-inventory.demo_stores', []);
        $storeName = $stores[$this->storeKey] ?? null;

        if ($storeName === null) {
            return;
        }

        $this->sheet['store_key'] = $this->storeKey;
        $this->sheet['store_name'] = $storeName;
        $this->persistSheet(false);
    }

    public function loadAllProducts(): void
    {
        $this->bulkAddProducts(app(PurchasePlanningBulkLoad::class)->allProducts());
    }

    public function loadByCategory(): void
    {
        if (blank($this->loadCategoryId)) {
            Notification::make()
                ->warning()
                ->title('Search and select a category first')
                ->send();

            return;
        }

        $this->bulkAddProducts(
            app(PurchasePlanningBulkLoad::class)->byCategory((int) $this->loadCategoryId),
            'Loaded from: ' . $this->selectedCategoryLabel,
        );
    }

    public function loadLowStock(): void
    {
        $this->bulkAddProducts(app(PurchasePlanningBulkLoad::class)->lowStock());
    }

    public function loadOutOfStock(): void
    {
        $this->bulkAddProducts(app(PurchasePlanningBulkLoad::class)->outOfStock());
    }

    public function loadFromDraftQuotations(): void
    {
        $existingIds = collect($this->rows)->pluck('product_id')->all();
        $added = 0;

        foreach (app(PurchaseQuotationSheetRepository::class)->all() as $otherSheet) {
            if (($otherSheet['id'] ?? '') === $this->quotationId) {
                continue;
            }

            if (($otherSheet['status'] ?? '') !== 'draft') {
                continue;
            }

            foreach ($otherSheet['rows'] ?? [] as $row) {
                $productId = (int) ($row['product_id'] ?? 0);

                if ($productId === 0 || in_array($productId, $existingIds, true)) {
                    continue;
                }

                $this->rows[] = array_merge($row, [
                    'line_id' => (string) Str::uuid(),
                ]);
                $existingIds[] = $productId;
                $added++;
            }
        }

        if ($added === 0) {
            Notification::make()
                ->info()
                ->title('No products in other draft quotations')
                ->body('Draft quotations are unfinished sheets that were not saved yet.')
                ->send();

            return;
        }

        $this->persistSheet(false);

        Notification::make()
            ->success()
            ->title($added . ' product(s) copied from other draft quotations')
            ->send();
    }

    public function removeLine(string $lineId): void
    {
        $this->rows = array_values(array_filter(
            $this->rows,
            fn (array $row): bool => ($row['line_id'] ?? '') !== $lineId,
        ));

        $this->persistSheet(false);
    }

    public function updatedSheetTitle(): void
    {
        $this->sheet['title'] = trim($this->sheetTitle);
        $this->persistSheet(false);
    }

    public function updatedSheetDate(): void
    {
        $this->sheet['sheet_date'] = $this->sheetDate;
        $this->persistSheet(false);
    }

    public function updatedNotes(): void
    {
        $this->sheet['notes'] = trim($this->notes);
        $this->persistSheet(false);
    }

    public function updatedRows(): void
    {
        $this->persistSheet(false);
    }

    public function saveQuotation(): void
    {
        if ($this->rows === []) {
            Notification::make()
                ->warning()
                ->title('Add at least one product before saving')
                ->send();

            return;
        }

        if ($this->supplierId === null) {
            Notification::make()
                ->warning()
                ->title('Select a supplier')
                ->body('Choose which supplier this quotation will be sent to.')
                ->send();

            return;
        }

        $this->sheet['status'] = 'saved';
        $this->persistSheet(false);

        Notification::make()
            ->success()
            ->title('Quotation saved')
            ->send();

        $this->redirect(PurchaseQuotations::getUrl());
    }

    public function discardQuotation(): void
    {
        app(PurchaseQuotationSheetRepository::class)->delete($this->quotationId);

        Notification::make()
            ->success()
            ->title('Quotation discarded')
            ->send();

        $this->redirect(PurchaseQuotations::getUrl());
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    protected function hydrateFromSheet(array $sheet): void
    {
        $this->sheet = $sheet;
        $this->rows = array_values($sheet['rows'] ?? []);
        $this->nameLang = (string) ($sheet['name_lang'] ?? 'both');
        $this->sheetTitle = (string) ($sheet['title'] ?? '');
        $this->sheetDate = (string) ($sheet['sheet_date'] ?? now()->toDateString());
        $this->notes = (string) ($sheet['notes'] ?? '');
        $this->supplierId = isset($sheet['supplier_id']) ? (int) $sheet['supplier_id'] : null;
        $this->storeKey = (string) ($sheet['store_key'] ?? config('purchasing-inventory.demo_default_store', 'main'));
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function addProduct(array $product): void
    {
        $productId = (int) ($product['id'] ?? 0);

        if ($productId === 0) {
            return;
        }

        foreach ($this->rows as $row) {
            if ((int) ($row['product_id'] ?? 0) === $productId) {
                Notification::make()
                    ->info()
                    ->title('Product already on this quotation')
                    ->send();

                return;
            }
        }

        $this->rows[] = app(PurchaseQuotationLineBuilder::class)->fromProduct($product);
        $this->persistSheet(false);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    protected function bulkAddProducts(array $products, ?string $successContext = null): void
    {
        if ($products === []) {
            Notification::make()
                ->info()
                ->title('No matching products found')
                ->send();

            return;
        }

        $existingIds = collect($this->rows)->pluck('product_id')->all();
        $added = 0;

        foreach ($products as $product) {
            $productId = (int) ($product['id'] ?? 0);

            if ($productId === 0 || in_array($productId, $existingIds, true)) {
                continue;
            }

            $this->rows[] = app(PurchaseQuotationLineBuilder::class)->fromProduct($product);
            $existingIds[] = $productId;
            $added++;
        }

        $this->persistSheet(false);

        if ($added === 0) {
            Notification::make()
                ->info()
                ->title('All matching products are already on this quotation')
                ->send();

            return;
        }

        $notification = Notification::make()
            ->success()
            ->title($added . ' product(s) added');

        if (filled($successContext)) {
            $notification->body($successContext);
        }

        $notification->send();
    }

    protected function persistSheet(bool $refreshSheet = true): void
    {
        $this->sheet['rows'] = $this->rows;
        $this->sheet['title'] = trim($this->sheetTitle);
        $this->sheet['sheet_date'] = $this->sheetDate;
        $this->sheet['notes'] = trim($this->notes);
        $this->sheet['name_lang'] = $this->nameLang;
        $this->sheet['supplier_id'] = $this->supplierId;
        $this->sheet['supplier_name'] = $this->supplierId !== null
            ? (string) ($this->supplierOptions()[$this->supplierId] ?? $this->sheet['supplier_name'] ?? '')
            : '';

        $stores = config('purchasing-inventory.demo_stores', []);
        $this->sheet['store_key'] = $this->storeKey;
        $this->sheet['store_name'] = (string) ($stores[$this->storeKey] ?? $this->sheet['store_name'] ?? '');

        app(PurchaseQuotationSheetRepository::class)->update($this->sheet);

        if ($refreshSheet) {
            $this->hydrateFromSheet(
                app(PurchaseQuotationSheetRepository::class)->find($this->quotationId) ?? $this->sheet,
            );
        }
    }
}
