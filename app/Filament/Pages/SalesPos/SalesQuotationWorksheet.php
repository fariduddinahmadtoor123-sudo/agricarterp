<?php

namespace App\Filament\Pages\SalesPos;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Models\Customer;
use App\Models\SalesPos\SalesQuotation;
use App\Services\SalesPos\PosCustomerSearch;
use App\Services\SalesPos\PosProductSearch;
use App\Services\SalesPos\PosSaleLineBuilder;
use App\Services\Settings\CompanySettingResolver;
use App\Support\SalesPos\SalesQuotationRepository;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class SalesQuotationWorksheet extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'sales-pos/sales-quotations/{quotationId}';

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

    public string $quotationDate = '';

    public string $notes = '';

    public ?int $customerId = null;

    public string $heldLabel = '';

    public string $printPaperSize = '80mm';

    public string $loadHeldQuotationId = '';

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
        return 'sales-quotations';
    }

    public function mount(string $quotationId): void
    {
        $sheet = app(SalesQuotationRepository::class)->find($quotationId);

        abort_if($sheet === null, 404);

        $this->quotationId = $quotationId;
        $this->hydrateFromSheet($sheet);
    }

    public function getTitle(): string | Htmlable
    {
        return (string) ($this->sheet['quotation_number'] ?? 'Sales Quotation');
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function content(Schema $schema): Schema
    {
        $company = app(CompanySettingResolver::class);
        $logoUrl = $company->logoUrl();

        return $schema->components([
            View::make('filament.sales-pos.sales-quotation-worksheet')
                ->viewData(fn (): array => [
                    'quotationNumber' => (string) ($this->sheet['quotation_number'] ?? ''),
                    'sheet' => $this->sheet,
                    'isEditable' => $this->isEditable(),
                    'quotationDate' => $this->quotationDate,
                    'nameLang' => $this->nameLang,
                    'notes' => $this->notes,
                    'rows' => $this->rows,
                    'itemCount' => count($this->rows),
                    'searchResults' => $this->searchResults,
                    'productSearch' => $this->productSearch,
                    'customerId' => $this->customerId,
                    'customerSearch' => $this->customerSearch,
                    'showCustomerDropdown' => $this->showCustomerDropdown,
                    'customerSearchResults' => $this->customerSearchResults,
                    'productSearchMinChars' => (int) config('sales-pos.product_search_min_chars', 2),
                    'subtotal' => PosSaleLineBuilder::formatAmount(PosSaleLineBuilder::subtotal($this->rows)),
                    'total' => PosSaleLineBuilder::formatAmount(PosSaleLineBuilder::subtotal($this->rows)),
                    'heldQuotations' => $this->heldQuotationOptions(),
                    'loadHeldQuotationId' => $this->loadHeldQuotationId,
                    'heldLabel' => $this->heldLabel,
                    'printPaperSizes' => config('sales-pos.print_paper_sizes', []),
                    'printPaperSize' => $this->printPaperSize,
                    'saleControls' => PosSaleLineBuilder::controlsByProduct($this->rows, $this->nameLang),
                    'printControls' => $this->printControls,
                    'quotationTotalLabel' => $this->nameLang === 'ur'
                        ? config('sales-pos.quotation_total_label_ur', 'کوٹیشن کل رقم')
                        : config('sales-pos.quotation_total_label', 'Quotation Total'),
                    'quotationAmountLabel' => $this->nameLang === 'ur'
                        ? config('sales-pos.quotation_print_amount_label_ur', 'کوٹیشن رقم')
                        : config('sales-pos.quotation_print_amount_label', 'Quotation Amount'),
                    'companyNameEn' => $company->nameEn(),
                    'companyNameUr' => $company->nameUr(),
                    'companyLogoUrl' => $this->absoluteUrl($logoUrl),
                    'companyPhones' => $company->phones(),
                    'companyEmails' => $company->emails(),
                    'companyAddressEn' => $company->addressEn(),
                    'companyAddressUr' => $company->addressUr(),
                    'companyWebsiteUrl' => $company->websiteUrl(),
                    'currencyCode' => $company->currency(),
                ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function heldQuotationOptions(): array
    {
        $options = [];

        foreach (app(SalesQuotationRepository::class)->held() as $held) {
            if (($held['id'] ?? '') === $this->quotationId) {
                continue;
            }

            $label = (string) ($held['held_label'] ?? $held['quotation_number'] ?? 'Held quotation');
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

    public function updatedQuotationDate(): void
    {
        $this->sheet['quotation_date'] = $this->quotationDate;
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

    public function updatedHeldLabel(): void
    {
        $this->sheet['held_label'] = trim($this->heldLabel) ?: null;
        $this->persistSheet(false);
    }

    public function holdQuotation(): void
    {
        if (! $this->isEditable()) {
            return;
        }

        if ($this->rows === []) {
            Notification::make()->warning()->title('Add products before holding the quotation')->send();

            return;
        }

        $this->sheet['status'] = SalesQuotation::STATUS_HELD;
        $this->sheet['held_label'] = filled($this->heldLabel)
            ? trim($this->heldLabel)
            : ('Hold ' . now()->format('H:i') . ' — ' . (string) ($this->sheet['customer_name'] ?? ''));
        $this->persistSheet(false);

        Notification::make()
            ->success()
            ->title('Quotation placed on hold')
            ->body('You can load it later from Held Quotations.')
            ->send();

        $this->redirect(SalesQuotations::getUrl());
    }

    public function loadHeldQuotation(): void
    {
        if (blank($this->loadHeldQuotationId)) {
            return;
        }

        $this->redirect(static::getUrl(['quotationId' => $this->loadHeldQuotationId]));
    }

    public function finalizeQuotation(): void
    {
        if (! $this->isEditable()) {
            return;
        }

        try {
            $finalized = app(SalesQuotationRepository::class)->finalize($this->buildSheetPayload());
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Could not finalize quotation')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Validation failed.')
                ->send();

            return;
        }

        $this->hydrateFromSheet($finalized);

        Notification::make()
            ->success()
            ->title('Quotation finalized')
            ->body('You can print the quotation now.')
            ->send();
    }

    public function startNewQuotation(): void
    {
        $sheet = app(SalesQuotationRepository::class)->create();

        $this->redirect(static::getUrl(['quotationId' => $sheet['id']]));
    }

    public function discardQuotation(): void
    {
        if (($this->sheet['status'] ?? '') === SalesQuotation::STATUS_FINALIZED) {
            return;
        }

        app(SalesQuotationRepository::class)->delete($this->quotationId);

        Notification::make()->success()->title('Quotation discarded')->send();

        $this->redirect(SalesQuotations::getUrl());
    }

    protected function isEditable(): bool
    {
        return in_array($this->sheet['status'] ?? '', [SalesQuotation::STATUS_DRAFT, SalesQuotation::STATUS_HELD], true);
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    protected function hydrateFromSheet(array $sheet): void
    {
        $this->sheet = $sheet;
        $this->rows = array_values($sheet['rows'] ?? []);
        $this->nameLang = (string) ($sheet['name_lang'] ?? 'both');
        $this->quotationDate = (string) ($sheet['quotation_date'] ?? now()->toDateString());
        $this->notes = (string) ($sheet['notes'] ?? '');
        $this->customerId = isset($sheet['customer_id']) ? (int) $sheet['customer_id'] : null;
        $this->customerSearch = $this->customerId !== null
            ? ($this->customerLabel((int) $this->customerId) ?: (string) ($sheet['customer_name'] ?? ''))
            : '';
        $this->showCustomerDropdown = false;
        $this->customerSearchResults = [];
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
            'quotation_date' => $this->quotationDate,
            'name_lang' => $this->nameLang,
            'notes' => trim($this->notes),
            'customer_id' => $this->customerId,
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
            'quotation_date' => $this->quotationDate,
            'name_lang' => $this->nameLang,
            'notes' => trim($this->notes),
            'customer_id' => $this->customerId,
            'held_label' => $this->heldLabel,
            'print_paper_size' => $this->printPaperSize,
            'print_controls' => $this->printControls,
            'rows' => $this->rows,
        ];

        app(SalesQuotationRepository::class)->update($this->sheet);

        if ($notify) {
            Notification::make()->success()->title('Quotation saved')->send();
        }
    }

    protected function absoluteUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        return str_starts_with($url, 'http') ? $url : url($url);
    }
}
