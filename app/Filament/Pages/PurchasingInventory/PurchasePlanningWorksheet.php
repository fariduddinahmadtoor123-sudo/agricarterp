<?php

namespace App\Filament\Pages\PurchasingInventory;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Services\PurchasingInventory\PurchasePlanningBulkLoad;
use App\Services\PurchasingInventory\PurchasePlanningCategorySearch;
use App\Services\PurchasingInventory\PurchasePlanningLineBuilder;
use App\Services\PurchasingInventory\PurchasePlanningProductSearch;
use App\Support\PurchasingInventory\PurchasePlanningSheetRepository;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class PurchasePlanningWorksheet extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'purchasing-inventory/purchase-planning/{sheetId}';

    protected static bool $shouldRegisterNavigation = false;

    public string $sheetId = '';

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
        return 'purchase-planning';
    }

    public function mount(string $sheetId): void
    {
        $sheet = app(PurchasePlanningSheetRepository::class)->find($sheetId);

        abort_if($sheet === null, 404);

        $this->sheetId = $sheetId;
        $this->hydrateFromSheet($sheet);
    }

    public function getTitle(): string | Htmlable
    {
        return (string) ($this->sheet['sheet_number'] ?? 'Planning Sheet');
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.purchasing-inventory.purchase-planning-worksheet')
                ->viewData(fn (): array => [
                    'itemCount' => count($this->rows),
                    'sheetNumber' => (string) ($this->sheet['sheet_number'] ?? ''),
                    'sheet' => $this->sheet,
                    'isNewSheet' => ($this->sheet['status'] ?? 'draft') === 'draft' && $this->rows === [],
                    'sheetTitle' => $this->sheetTitle,
                    'nameLang' => $this->nameLang,
                    'searchResults' => $this->searchResults,
                    'categorySearch' => $this->categorySearch,
                    'categorySearchResults' => $this->categorySearchResults,
                    'selectedCategoryLabel' => $this->selectedCategoryLabel,
                    'rows' => $this->rows,
                    'minVisualRows' => (int) config('purchasing-inventory.worksheet_min_visual_rows', 10),
                    'sheetDate' => $this->sheetDate,
                    'sheetTitle' => $this->sheetTitle,
                    'notes' => $this->notes,
                ]),
        ]);
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

    public function loadFromDraftSheets(): void
    {
        $existingIds = collect($this->rows)->pluck('product_id')->all();
        $added = 0;

        foreach (app(PurchasePlanningSheetRepository::class)->all() as $otherSheet) {
            if (($otherSheet['id'] ?? '') === $this->sheetId) {
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
                    'line_id' => (string) \Illuminate\Support\Str::uuid(),
                ]);
                $existingIds[] = $productId;
                $added++;
            }
        }

        if ($added === 0) {
            Notification::make()
                ->info()
                ->title('No products in other draft sheets')
                ->body('Draft sheets are unfinished sheets that were not saved yet. Save a sheet or leave it open without saving to create a draft.')
                ->send();

            return;
        }

        $this->persistSheet(false);

        Notification::make()
            ->success()
            ->title($added . ' product(s) copied from other draft sheets')
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

    public function saveSheet(): void
    {
        if ($this->rows === []) {
            Notification::make()
                ->warning()
                ->title('Add at least one product before saving')
                ->send();

            return;
        }

        $this->sheet['status'] = 'saved';
        $this->persistSheet(false);

        Notification::make()
            ->success()
            ->title('Planning sheet saved')
            ->send();

        $this->redirect(PurchasePlanning::getUrl());
    }

    public function discardSheet(): void
    {
        app(PurchasePlanningSheetRepository::class)->delete($this->sheetId);

        Notification::make()
            ->success()
            ->title('Planning sheet discarded')
            ->send();

        $this->redirect(PurchasePlanning::getUrl());
    }

    public function displayName(array $row): string
    {
        return PurchasePlanningLineBuilder::displayName($row, $this->nameLang);
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
                    ->title('Product already on this sheet')
                    ->send();

                return;
            }
        }

        $this->rows[] = app(PurchasePlanningLineBuilder::class)->fromProduct($product);
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

            $this->rows[] = app(PurchasePlanningLineBuilder::class)->fromProduct($product);
            $existingIds[] = $productId;
            $added++;
        }

        $this->persistSheet(false);

        if ($added === 0) {
            Notification::make()
                ->info()
                ->title('All matching products are already on this sheet')
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

        app(PurchasePlanningSheetRepository::class)->update($this->sheet);

        if ($refreshSheet) {
            $this->hydrateFromSheet(
                app(PurchasePlanningSheetRepository::class)->find($this->sheetId) ?? $this->sheet,
            );
        }
    }
}
