<?php

namespace App\Filament\Pages\SalesPos;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Models\Customer;
use App\Models\SalesPos\PosSale;
use App\Services\SalesPos\PosCustomerSearch;
use App\Services\SalesPos\PosProductSearch;
use App\Services\SalesPos\PosSaleLineBuilder;
use App\Services\SalesPos\PosSaleReturnSummary;
use App\Services\Settings\CompanySettingResolver;
use App\Support\SalesPos\PosSaleRepository;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class PosSaleWorksheet extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'sales-pos/pos-sales/{saleId}';

    protected static bool $shouldRegisterNavigation = false;

    public string $saleId = '';

    /** @var array<string, mixed> */
    public array $sheet = [];

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public string $productSearch = '';

    /** @var list<array<string, mixed>> */
    public array $searchResults = [];

    public string $nameLang = 'both';

    public string $saleDate = '';

    public string $notes = '';

    public ?int $customerId = null;

    public string $paymentMethod = 'cash';

    public string $amountPaid = '';

    public string $heldLabel = '';

    public string $printPaperSize = '80mm';

    public string $loadHeldSaleId = '';

    public string $customerSearch = '';

    public bool $showCustomerDropdown = false;

    /** @var array<int, string> */
    public array $customerSearchResults = [];

    public bool $printControls = false;

    public static function moduleKey(): string
    {
        return 'sales-pos';
    }

    public static function submenuKey(): string
    {
        return 'pos-sales';
    }

    public function mount(string $saleId): void
    {
        $sheet = app(PosSaleRepository::class)->find($saleId);

        abort_if($sheet === null, 404);

        $this->saleId = $saleId;
        $this->hydrateFromSheet($sheet);
    }

    public function getTitle(): string | Htmlable
    {
        return (string) ($this->sheet['sale_number'] ?? 'POS Sale');
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function content(Schema $schema): Schema
    {
        $company = app(CompanySettingResolver::class);
        $logoUrl = $company->logoUrl();
        $returnSummary = ($this->sheet['status'] ?? '') === PosSale::STATUS_COMPLETED
            ? ($this->sheet['return_summary'] ?? app(PosSaleReturnSummary::class)->forSale($this->saleId))
            : null;
        $displayRows = $this->rowsWithReturnQty($returnSummary);

        return $schema->components([
            View::make('filament.sales-pos.pos-sale-worksheet')
                ->viewData(fn (): array => [
                    'saleNumber' => (string) ($this->sheet['sale_number'] ?? ''),
                    'sheet' => $this->sheet,
                    'isEditable' => $this->isEditable(),
                    'saleDate' => $this->saleDate,
                    'nameLang' => $this->nameLang,
                    'notes' => $this->notes,
                    'rows' => $displayRows,
                    'itemCount' => count($this->rows),
                    'searchResults' => $this->searchResults,
                    'productSearch' => $this->productSearch,
                    'customerId' => $this->customerId,
                    'customerSearch' => $this->customerSearch,
                    'showCustomerDropdown' => $this->showCustomerDropdown,
                    'customerSearchResults' => $this->customerSearchResults,
                    'productSearchMinChars' => (int) config('sales-pos.product_search_min_chars', 2),
                    'paymentMethods' => config('sales-pos.payment_methods', []),
                    'paymentMethod' => $this->paymentMethod,
                    'amountPaid' => $this->amountPaid,
                    'subtotal' => PosSaleLineBuilder::formatAmount(PosSaleLineBuilder::subtotal($this->rows)),
                    'total' => PosSaleLineBuilder::formatAmount(PosSaleLineBuilder::subtotal($this->rows)),
                    'changeDue' => $this->changeDueLabel(),
                    'heldSales' => $this->heldSaleOptions(),
                    'loadHeldSaleId' => $this->loadHeldSaleId,
                    'heldLabel' => $this->heldLabel,
                    'printPaperSizes' => config('sales-pos.print_paper_sizes', []),
                    'printPaperSize' => $this->printPaperSize,
                    'storeOptions' => config('purchasing-inventory.demo_stores', []),
                    'storeKey' => (string) ($this->sheet['store_key'] ?? ''),
                    'saleControls' => PosSaleLineBuilder::controlsByProduct($this->rows, $this->nameLang),
                    'printControls' => $this->printControls,
                    'companyNameEn' => $company->nameEn(),
                    'companyNameUr' => $company->nameUr(),
                    'companyLogoUrl' => $this->absoluteUrl($logoUrl),
                    'companyPhones' => $company->phones(),
                    'companyEmails' => $company->emails(),
                    'companyAddressEn' => $company->addressEn(),
                    'companyAddressUr' => $company->addressUr(),
                    'companyWebsiteUrl' => $company->websiteUrl(),
                    'currencyCode' => $company->currency(),
                    'returnSummary' => $returnSummary ?? ['has_returns' => false, 'returns' => []],
                    'heldSalesPageUrl' => HeldSales::getUrl(),
                ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function heldSaleOptions(): array
    {
        $options = [];

        foreach (app(PosSaleRepository::class)->held() as $held) {
            if (($held['id'] ?? '') === $this->saleId) {
                continue;
            }

            $label = (string) ($held['held_label'] ?? $held['sale_number'] ?? 'Held sale');
            $customer = (string) ($held['customer_name'] ?? '');
            $options[(string) $held['id']] = $label . ($customer !== '' ? ' · ' . $customer : '');
        }

        return $options;
    }

    public function updatedCustomerSearch(): void
    {
        $term = trim($this->customerSearch);

        if ($term === '') {
            $this->customerId = null;
            $this->showCustomerDropdown = false;
            $this->customerSearchResults = [];
            $this->updatedCustomerId();

            return;
        }

        $this->showCustomerDropdown = true;

        if (mb_strlen($term) < (int) config('sales-pos.customer_search_min_chars', 2)) {
            $this->customerSearchResults = [];

            return;
        }

        $this->customerSearchResults = app(PosCustomerSearch::class)->search(
            $term,
            (int) config('sales-pos.customer_search_limit', 15),
        );
    }

    public function selectCustomer(int $customerId): void
    {
        $this->customerId = $customerId;
        $this->customerSearch = $this->customerSearchResults[$customerId]
            ?? $this->customerLabel($customerId);
        $this->showCustomerDropdown = false;
        $this->customerSearchResults = [];
        $this->updatedCustomerId();
    }

    protected function customerLabel(int $customerId): string
    {
        $customer = Customer::operational()->find($customerId);

        if ($customer === null) {
            return '';
        }

        return trim($customer->customer_code . ' — ' . $customer->customer_name);
    }

    public function updatedProductSearch(): void
    {
        $term = trim($this->productSearch);
        $minChars = (int) config('sales-pos.product_search_min_chars', 2);

        if (mb_strlen($term) < $minChars) {
            $this->searchResults = [];

            return;
        }

        $this->searchResults = app(PosProductSearch::class)->search(
            $term,
            (string) ($this->sheet['store_key'] ?? null),
        );
    }

    public function addProductFromSearch(): void
    {
        if (! $this->isEditable()) {
            return;
        }

        $term = trim($this->productSearch);

        if ($term === '') {
            return;
        }

        $search = app(PosProductSearch::class);
        $storeKey = (string) ($this->sheet['store_key'] ?? null);
        $product = $search->findExactMatch($term, $storeKey)
            ?? ($this->searchResults[0] ?? null)
            ?? ($search->search($term, $storeKey, 1)[0] ?? null);

        if ($product === null) {
            Notification::make()->warning()->title('Product not found')->send();

            return;
        }

        $this->addProduct($product);
        $this->productSearch = '';
        $this->searchResults = [];

        $this->dispatch('pos-focus-sidebar-search');
    }

    public function selectSearchResult(int $productId): void
    {
        if (! $this->isEditable()) {
            return;
        }

        $product = app(PosProductSearch::class)->findById(
            $productId,
            (string) ($this->sheet['store_key'] ?? null),
        );

        if ($product === null) {
            return;
        }

        $this->addProduct($product);
        $this->productSearch = '';
        $this->searchResults = [];

        $this->dispatch('pos-focus-sidebar-search');
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public function addProduct(array $product): void
    {
        $productId = (int) ($product['id'] ?? 0);

        foreach ($this->rows as $index => $row) {
            if ((int) ($row['product_id'] ?? 0) === $productId) {
                $qty = PosSaleLineBuilder::numeric($row['qty'] ?? '') + 1;
                $this->rows[$index]['qty'] = PosSaleLineBuilder::formatQuantity($qty);
                $this->rows[$index] = PosSaleLineBuilder::recalculate($this->rows[$index]);
                $this->persistSheet(false);

                return;
            }
        }

        $this->rows[] = app(PosSaleLineBuilder::class)->fromProduct($product);
        $this->persistSheet(false);

        $this->dispatch('pos-focus-sidebar-search');
    }

    public function removeRow(string $lineId): void
    {
        if (! $this->isEditable()) {
            return;
        }

        $this->rows = array_values(array_filter(
            $this->rows,
            fn (array $row): bool => (string) ($row['line_id'] ?? '') !== $lineId,
        ));
        $this->persistSheet(false);
    }

    public function updatedRows(): void
    {
        $this->rows = array_map(
            fn (array $row): array => PosSaleLineBuilder::recalculate($row),
            $this->rows,
        );
        $this->persistSheet(false);
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

    public function updatedCustomerId(): void
    {
        if ($this->customerId === null) {
            $this->sheet['customer_id'] = null;
            $this->sheet['customer_name'] = config('sales-pos.walk_in_customer_label', 'Walk-in Customer');
            $this->sheet['customer_mobile'] = null;
            if (trim($this->customerSearch) === '') {
                $this->customerSearch = '';
            }
            $this->persistSheet(false);

            return;
        }

        $customer = Customer::query()->find($this->customerId);

        if ($customer === null) {
            return;
        }

        $this->sheet['customer_id'] = $customer->id;
        $this->sheet['customer_name'] = (string) $customer->customer_name;
        $this->sheet['customer_mobile'] = (string) $customer->mobile_number;
        $this->customerSearch = trim($customer->customer_code . ' — ' . $customer->customer_name);
        $this->persistSheet(false);
    }

    public function updatedPaymentMethod(): void
    {
        $this->sheet['payment_method'] = $this->paymentMethod;
        $this->persistSheet(false);
    }

    public function updatedAmountPaid(): void
    {
        $this->sheet['amount_paid'] = $this->amountPaid;
        $this->persistSheet(false);
    }

    public function updatedSaleDate(): void
    {
        $this->sheet['sale_date'] = $this->saleDate;
        $this->persistSheet(false);
    }

    public function updatedNotes(): void
    {
        $this->sheet['notes'] = trim($this->notes);
        $this->persistSheet(false);
    }

    public function updatedPrintPaperSize(): void
    {
        $this->sheet['print_paper_size'] = $this->printPaperSize;
        $this->persistSheet(false);
    }

    public function updatedPrintControls(): void
    {
        $this->sheet['print_controls'] = $this->printControls;
        $this->persistSheet(false);
    }

    public function fillExactAmount(): void
    {
        $this->amountPaid = PosSaleLineBuilder::formatAmount(PosSaleLineBuilder::subtotal($this->rows));
        $this->sheet['amount_paid'] = $this->amountPaid;
        $this->persistSheet(false);
    }

    public function updatedHeldLabel(): void
    {
        $this->sheet['held_label'] = trim($this->heldLabel) ?: null;
        $this->persistSheet(false);
    }

    public function holdSale(): void
    {
        if (! $this->isEditable()) {
            return;
        }

        if ($this->rows === []) {
            Notification::make()->warning()->title('Add products before holding the sale')->send();

            return;
        }

        $this->sheet['status'] = PosSale::STATUS_HELD;
        $this->sheet['held_label'] = filled($this->heldLabel)
            ? trim($this->heldLabel)
            : ('Hold ' . now()->format('H:i') . ' — ' . (string) ($this->sheet['customer_name'] ?? ''));
        $this->persistSheet(false);

        Notification::make()
            ->success()
            ->title('Sale placed on hold')
            ->body('You can load it later from Held Sales.')
            ->send();

        $this->redirect(PosSales::getUrl());
    }

    public function loadHeldSale(): void
    {
        if (blank($this->loadHeldSaleId)) {
            return;
        }

        $this->redirect(static::getUrl(['saleId' => $this->loadHeldSaleId]));
    }

    public function completeSale(): void
    {
        if (! $this->isEditable()) {
            return;
        }

        try {
            $completed = app(PosSaleRepository::class)->complete($this->buildSheetPayload());
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Could not complete sale')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Validation failed.')
                ->send();

            return;
        }

        $this->hydrateFromSheet($completed);

        Notification::make()
            ->success()
            ->title('Sale completed')
            ->body('Stock updated. You can print the receipt now.')
            ->send();
    }

    public function startNewSale(): void
    {
        $sheet = app(PosSaleRepository::class)->create();

        $this->redirect(static::getUrl(['saleId' => $sheet['id']]));
    }

    public function discardSale(): void
    {
        if (($this->sheet['status'] ?? '') === PosSale::STATUS_COMPLETED) {
            return;
        }

        app(PosSaleRepository::class)->delete($this->saleId);

        Notification::make()->success()->title('Sale discarded')->send();

        $this->redirect(PosSales::getUrl());
    }

    protected function isEditable(): bool
    {
        return in_array($this->sheet['status'] ?? '', [PosSale::STATUS_DRAFT, PosSale::STATUS_HELD], true);
    }

    protected function changeDueLabel(): string
    {
        $paid = PosSaleLineBuilder::numeric($this->amountPaid);
        $total = PosSaleLineBuilder::subtotal($this->rows);
        $change = $paid - $total;

        if ($paid <= 0 || $change <= 0) {
            return '';
        }

        return PosSaleLineBuilder::formatAmount($change);
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    protected function hydrateFromSheet(array $sheet): void
    {
        $this->sheet = $sheet;
        $this->rows = array_values($sheet['rows'] ?? []);
        $this->nameLang = (string) ($sheet['name_lang'] ?? 'both');
        $this->saleDate = (string) ($sheet['sale_date'] ?? now()->toDateString());
        $this->notes = (string) ($sheet['notes'] ?? '');
        $this->customerId = isset($sheet['customer_id']) ? (int) $sheet['customer_id'] : null;
        $this->customerSearch = $this->customerId !== null
            ? ($this->customerLabel((int) $this->customerId) ?: (string) ($sheet['customer_name'] ?? ''))
            : '';
        $this->showCustomerDropdown = false;
        $this->customerSearchResults = [];
        $this->paymentMethod = (string) ($sheet['payment_method'] ?? config('sales-pos.default_payment_method', 'cash'));
        $this->amountPaid = (string) ($sheet['amount_paid'] ?? '');
        $this->heldLabel = (string) ($sheet['held_label'] ?? '');
        $this->printPaperSize = (string) ($sheet['print_paper_size'] ?? '80mm');
        $this->printControls = (bool) ($sheet['print_controls'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSheetPayload(): array
    {
        return [
            ...$this->sheet,
            'sale_date' => $this->saleDate,
            'name_lang' => $this->nameLang,
            'notes' => trim($this->notes),
            'customer_id' => $this->customerId,
            'payment_method' => $this->paymentMethod,
            'amount_paid' => $this->amountPaid,
            'held_label' => $this->heldLabel,
            'print_paper_size' => $this->printPaperSize,
            'print_controls' => $this->printControls,
            'rows' => $this->rows,
        ];
    }

    protected function persistSheet(bool $notify = false): void
    {
        if (! $this->isEditable()) {
            return;
        }

        $this->sheet = [
            ...$this->sheet,
            'sale_date' => $this->saleDate,
            'name_lang' => $this->nameLang,
            'notes' => trim($this->notes),
            'customer_id' => $this->customerId,
            'payment_method' => $this->paymentMethod,
            'amount_paid' => $this->amountPaid,
            'held_label' => $this->heldLabel,
            'print_paper_size' => $this->printPaperSize,
            'print_controls' => $this->printControls,
            'rows' => $this->rows,
        ];

        app(PosSaleRepository::class)->update($this->sheet);

        if ($notify) {
            Notification::make()->success()->title('Sale saved')->send();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function displayName(array $row): string
    {
        return PosSaleLineBuilder::displayName($row, $this->nameLang);
    }

    protected function absoluteUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        return str_starts_with($url, 'http') ? $url : url($url);
    }

    /**
     * @param  array<string, mixed>|null  $returnSummary
     * @return list<array<string, mixed>>
     */
    protected function rowsWithReturnQty(?array $returnSummary): array
    {
        if ($returnSummary === null || ! ($returnSummary['has_returns'] ?? false)) {
            return $this->rows;
        }

        $returnedByLine = $returnSummary['returned_qty_by_line'] ?? [];

        return array_map(function (array $row) use ($returnedByLine): array {
            $lineId = (string) ($row['line_id'] ?? '');
            $returned = (float) ($returnedByLine[$lineId] ?? 0);

            if ($returned > 0) {
                $row['returned_qty'] = PosSaleLineBuilder::formatQuantity($returned);
            }

            return $row;
        }, $this->rows);
    }
}
