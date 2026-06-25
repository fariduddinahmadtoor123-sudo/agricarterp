<?php

namespace App\Filament\Pages\PurchasingInventory;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Models\Supplier;
use App\Services\PurchasingInventory\InventoryService;
use App\Services\PurchasingInventory\PurchaseLineBuilder;
use App\Services\Settings\PurchasePricingSettingResolver;
use App\Services\PurchasingInventory\PurchasePlanningBulkLoad;
use App\Services\PurchasingInventory\PurchasePlanningCategorySearch;
use App\Services\PurchasingInventory\PurchasePlanningProductSearch;
use App\Services\PurchasingInventory\PurchaseSheetImportService;
use App\Support\PurchasingInventory\PurchaseSheetRepository;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class PurchaseWorksheet extends Page
{
    use InteractsWithModuleSubmenuPage;
    use WithFileUploads;

    protected static ?string $slug = 'purchasing-inventory/purchases/{purchaseId}';

    protected static bool $shouldRegisterNavigation = false;

    public string $purchaseId = '';

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

    public string $invoicePaymentStatus = 'unpaid';

    public string $goodsReceiptStatus = 'pending';

    public string $disputeStatus = 'none';

    public string $disputeNotes = '';

    public string $paymentAmount = '';

    public string $paymentNotes = '';

    public string $printPaperSize = 'a4';

    public string $linkPlanningId = '';

    public string $linkQuotationId = '';

    public ?TemporaryUploadedFile $invoiceImageUpload = null;

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
        return 'purchases';
    }

    public function mount(string $purchaseId): void
    {
        $sheet = app(PurchaseSheetRepository::class)->find($purchaseId);

        abort_if($sheet === null, 404);

        $this->purchaseId = $purchaseId;
        $this->hydrateFromSheet($sheet);
    }

    public function getTitle(): string | Htmlable
    {
        return (string) ($this->sheet['purchase_number'] ?? 'Purchase Invoice');
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function content(Schema $schema): Schema
    {
        $import = app(PurchaseSheetImportService::class);

        return $schema->components([
            View::make('filament.purchasing-inventory.purchase-worksheet')
                ->viewData(fn (): array => [
                    'itemCount' => count($this->rows),
                    'purchaseNumber' => (string) ($this->sheet['purchase_number'] ?? ''),
                    'sheet' => $this->sheet,
                    'isNewPurchase' => ($this->sheet['status'] ?? 'draft') === 'draft' && $this->rows === [],
                    'sheetTitle' => $this->sheetTitle,
                    'sheetDate' => $this->sheetDate,
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
                    'invoicePaymentStatuses' => config('purchasing-inventory.purchase_invoice_payment_statuses', []),
                    'goodsReceiptStatuses' => config('purchasing-inventory.purchase_goods_receipt_statuses', []),
                    'disputeStatuses' => config('purchasing-inventory.purchase_dispute_statuses', []),
                    'invoicePaymentStatus' => $this->invoicePaymentStatus,
                    'goodsReceiptStatus' => $this->goodsReceiptStatus,
                    'disputeStatus' => $this->disputeStatus,
                    'disputeNotes' => $this->disputeNotes,
                    'paymentAmount' => $this->paymentAmount,
                    'paymentNotes' => $this->paymentNotes,
                    'printPaperSizes' => config('purchasing-inventory.purchase_print_paper_sizes', []),
                    'printPaperSize' => $this->printPaperSize,
                    'invoiceTotal' => PurchaseLineBuilder::invoiceTotal($this->rows),
                    'supplierBalance' => $import->supplierBalance($this->supplierId),
                    'planningOptions' => $import->planningOptions(),
                    'quotationOptions' => $import->quotationOptions(),
                    'linkPlanningId' => $this->linkPlanningId,
                    'linkQuotationId' => $this->linkQuotationId,
                    'invoiceImageUrl' => filled($this->sheet['invoice_image_path'] ?? null)
                        ? Storage::disk('public')->url((string) $this->sheet['invoice_image_path'])
                        : null,
                    'tierLabels' => app(PurchasePricingSettingResolver::class)->tierLabels(),
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

    public function updatedInvoicePaymentStatus(): void
    {
        $this->sheet['invoice_payment_status'] = $this->invoicePaymentStatus;
        $this->persistSheet(false);
    }

    public function updatedGoodsReceiptStatus(): void
    {
        $this->sheet['goods_receipt_status'] = $this->goodsReceiptStatus;
        $this->persistSheet(false);
    }

    public function updatedDisputeStatus(): void
    {
        $this->sheet['dispute_status'] = $this->disputeStatus;
        $this->persistSheet(false);
    }

    public function updatedDisputeNotes(): void
    {
        $this->sheet['dispute_notes'] = trim($this->disputeNotes);
        $this->persistSheet(false);
    }

    public function updatedPaymentAmount(): void
    {
        $this->sheet['payment_amount'] = trim($this->paymentAmount);
        $this->persistSheet(false);
    }

    public function updatedPaymentNotes(): void
    {
        $this->sheet['payment_notes'] = trim($this->paymentNotes);
        $this->persistSheet(false);
    }

    public function updatedPrintPaperSize(): void
    {
        $this->sheet['print_paper_size'] = $this->printPaperSize;
        $this->persistSheet(false);
    }

    public function updatedInvoiceImageUpload(): void
    {
        $this->validate([
            'invoiceImageUpload' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($this->invoiceImageUpload === null) {
            return;
        }

        if (filled($this->sheet['invoice_image_path'] ?? null)) {
            Storage::disk('public')->delete((string) $this->sheet['invoice_image_path']);
        }

        $path = $this->invoiceImageUpload->store('purchase-preview/' . $this->purchaseId, 'public');
        $this->sheet['invoice_image_path'] = $path;
        $this->invoiceImageUpload = null;
        $this->persistSheet(false);

        Notification::make()
            ->success()
            ->title('Supplier invoice image uploaded')
            ->send();
    }

    public function removeInvoiceImage(): void
    {
        if (filled($this->sheet['invoice_image_path'] ?? null)) {
            Storage::disk('public')->delete((string) $this->sheet['invoice_image_path']);
        }

        $this->sheet['invoice_image_path'] = null;
        $this->persistSheet(false);
    }

    public function recalculateRowTiers(int $index, bool $syncMarkupsFromSettings = false): void
    {
        if (! isset($this->rows[$index])) {
            return;
        }

        $this->rows[$index] = PurchaseLineBuilder::applyTierRates($this->rows[$index], $syncMarkupsFromSettings);
        $this->persistSheet(false);
    }

    public function recalculateRowTiersFromSettings(int $index): void
    {
        $this->recalculateRowTiers($index, true);
    }

    public function importPlanningSheet(): void
    {
        if ($this->linkPlanningId === '') {
            Notification::make()->warning()->title('Select a planning sheet first')->send();

            return;
        }

        $import = app(PurchaseSheetImportService::class)->fromPlanning($this->linkPlanningId);

        if ($import === null) {
            Notification::make()->warning()->title('Planning sheet not found')->send();

            return;
        }

        $this->applyImportedSheet($import);
        Notification::make()->success()->title('Products loaded from planning sheet')->send();
    }

    public function importQuotationSheet(): void
    {
        if ($this->linkQuotationId === '') {
            Notification::make()->warning()->title('Select a quotation first')->send();

            return;
        }

        $import = app(PurchaseSheetImportService::class)->fromQuotation($this->linkQuotationId);

        if ($import === null) {
            Notification::make()->warning()->title('Quotation not found')->send();

            return;
        }

        $this->applyImportedSheet($import);

        if (filled($import['meta']['supplier_id'] ?? null)) {
            $this->supplierId = (int) $import['meta']['supplier_id'];
            $this->sheet['supplier_id'] = $this->supplierId;
            $this->sheet['supplier_name'] = (string) ($import['meta']['supplier_name'] ?? '');
        }

        if (filled($import['meta']['store_key'] ?? null)) {
            $this->storeKey = (string) $import['meta']['store_key'];
            $this->sheet['store_key'] = $this->storeKey;
            $this->sheet['store_name'] = (string) ($import['meta']['store_name'] ?? '');
        }

        Notification::make()->success()->title('Products loaded from quotation')->send();
    }

    public function loadAllProducts(): void
    {
        $this->bulkAddProducts(app(PurchasePlanningBulkLoad::class)->allProducts());
    }

    public function loadByCategory(): void
    {
        if (blank($this->loadCategoryId)) {
            Notification::make()->warning()->title('Search and select a category first')->send();

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

    public function savePurchase(): void
    {
        if ($this->rows === []) {
            Notification::make()->warning()->title('Add at least one product before saving')->send();

            return;
        }

        if ($this->supplierId === null) {
            Notification::make()->warning()->title('Select a supplier')->send();

            return;
        }

        $this->sheet['status'] = 'saved';
        $this->persistSheet(false);

        Notification::make()->success()->title('Purchase invoice saved')->send();

        $this->redirect(Purchases::getUrl());
    }

    public function processGoodsReceipt(): void
    {
        if ($this->rows === []) {
            Notification::make()->warning()->title('Add products before receiving goods')->send();

            return;
        }

        if (($this->sheet['status'] ?? 'draft') !== 'saved') {
            Notification::make()->warning()->title('Save the invoice before processing goods receipt')->send();

            return;
        }

        try {
            $result = app(InventoryService::class)->receivePurchaseGoods(
                $this->sheet,
                $this->rows,
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Goods receipt failed')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Invalid quantities.')
                ->send();

            return;
        }

        $this->rows = $result['rows'];
        $this->goodsReceiptStatus = $result['goods_receipt_status'];
        $this->sheet['goods_receipt_status'] = $this->goodsReceiptStatus;
        $this->persistSheet(false);

        Notification::make()
            ->success()
            ->title('Goods receipt processed')
            ->body('Status: ' . (config('purchasing-inventory.purchase_goods_receipt_statuses.' . $this->goodsReceiptStatus) ?? $this->goodsReceiptStatus))
            ->send();
    }

    public function discardPurchase(): void
    {
        if (filled($this->sheet['invoice_image_path'] ?? null)) {
            Storage::disk('public')->delete((string) $this->sheet['invoice_image_path']);
        }

        app(PurchaseSheetRepository::class)->delete($this->purchaseId);

        Notification::make()->success()->title('Purchase invoice discarded')->send();

        $this->redirect(Purchases::getUrl());
    }

    /**
     * @param  array{rows: list<array<string, mixed>>, meta: array<string, mixed>}  $import
     */
    protected function applyImportedSheet(array $import): void
    {
        $this->rows = $import['rows'];
        $meta = $import['meta'];

        if (filled($meta['linked_planning_id'] ?? null)) {
            $this->sheet['linked_planning_id'] = $meta['linked_planning_id'];
            $this->sheet['linked_planning_number'] = (string) ($meta['linked_planning_number'] ?? '');
            $this->linkPlanningId = (string) $meta['linked_planning_id'];
        }

        if (filled($meta['linked_quotation_id'] ?? null)) {
            $this->sheet['linked_quotation_id'] = $meta['linked_quotation_id'];
            $this->sheet['linked_quotation_number'] = (string) ($meta['linked_quotation_number'] ?? '');
            $this->linkQuotationId = (string) $meta['linked_quotation_id'];
        }

        if (filled($meta['notes'] ?? null) && blank($this->notes)) {
            $this->notes = (string) $meta['notes'];
            $this->sheet['notes'] = $this->notes;
        }

        if (filled($meta['name_lang'] ?? null)) {
            $this->nameLang = (string) $meta['name_lang'];
            $this->sheet['name_lang'] = $this->nameLang;
        }

        $this->persistSheet(false);
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    protected function hydrateFromSheet(array $sheet): void
    {
        $this->sheet = $sheet;
        $this->rows = array_map(
            fn (array $row): array => PurchaseLineBuilder::normalizeRowQuantities($row),
            array_values($sheet['rows'] ?? []),
        );
        $this->nameLang = (string) ($sheet['name_lang'] ?? 'both');
        $this->sheetTitle = (string) ($sheet['title'] ?? '');
        $this->sheetDate = (string) ($sheet['sheet_date'] ?? now()->toDateString());
        $this->notes = (string) ($sheet['notes'] ?? '');
        $this->supplierId = isset($sheet['supplier_id']) ? (int) $sheet['supplier_id'] : null;
        $this->storeKey = (string) ($sheet['store_key'] ?? config('purchasing-inventory.demo_default_store', 'main'));
        $this->invoicePaymentStatus = (string) ($sheet['invoice_payment_status'] ?? 'unpaid');
        $this->goodsReceiptStatus = (string) ($sheet['goods_receipt_status'] ?? 'pending');
        $this->disputeStatus = (string) ($sheet['dispute_status'] ?? 'none');
        $this->disputeNotes = (string) ($sheet['dispute_notes'] ?? '');
        $this->paymentAmount = (string) ($sheet['payment_amount'] ?? '');
        $this->paymentNotes = (string) ($sheet['payment_notes'] ?? '');
        $this->printPaperSize = (string) ($sheet['print_paper_size'] ?? 'a4');
        $this->linkPlanningId = (string) ($sheet['linked_planning_id'] ?? '');
        $this->linkQuotationId = (string) ($sheet['linked_quotation_id'] ?? '');
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
                Notification::make()->info()->title('Product already on this invoice')->send();

                return;
            }
        }

        $this->rows[] = app(PurchaseLineBuilder::class)->fromProduct($product);
        $this->persistSheet(false);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    protected function bulkAddProducts(array $products, ?string $successContext = null): void
    {
        if ($products === []) {
            Notification::make()->info()->title('No matching products found')->send();

            return;
        }

        $existingIds = collect($this->rows)->pluck('product_id')->all();
        $added = 0;

        foreach ($products as $product) {
            $productId = (int) ($product['id'] ?? 0);

            if ($productId === 0 || in_array($productId, $existingIds, true)) {
                continue;
            }

            $this->rows[] = app(PurchaseLineBuilder::class)->fromProduct($product);
            $existingIds[] = $productId;
            $added++;
        }

        $this->persistSheet(false);

        if ($added === 0) {
            Notification::make()->info()->title('All matching products are already on this invoice')->send();

            return;
        }

        $notification = Notification::make()->success()->title($added . ' product(s) added');

        if (filled($successContext)) {
            $notification->body($successContext);
        }

        $notification->send();
    }

    protected function persistSheet(bool $refreshSheet = true): void
    {
        $this->rows = array_map(
            fn (array $row): array => PurchaseLineBuilder::normalizeRowQuantities($row),
            $this->rows,
        );

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
        $this->sheet['invoice_payment_status'] = $this->invoicePaymentStatus;
        $this->sheet['goods_receipt_status'] = $this->goodsReceiptStatus;
        $this->sheet['dispute_status'] = $this->disputeStatus;
        $this->sheet['dispute_notes'] = trim($this->disputeNotes);
        $this->sheet['payment_amount'] = trim($this->paymentAmount);
        $this->sheet['payment_notes'] = trim($this->paymentNotes);
        $this->sheet['print_paper_size'] = $this->printPaperSize;
        $this->sheet['linked_planning_id'] = $this->linkPlanningId !== '' ? $this->linkPlanningId : null;
        $this->sheet['linked_quotation_id'] = $this->linkQuotationId !== '' ? $this->linkQuotationId : null;

        app(PurchaseSheetRepository::class)->update($this->sheet);

        if ($refreshSheet) {
            $this->hydrateFromSheet(
                app(PurchaseSheetRepository::class)->find($this->purchaseId) ?? $this->sheet,
            );
        }
    }
}
