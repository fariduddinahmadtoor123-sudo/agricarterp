<?php

namespace App\Filament\Pages\PurchasingInventory;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Services\PurchasingInventory\PurchasePaymentSheetBuilder;
use App\Services\PurchasingInventory\PurchasePaymentSupplierSearch;
use App\Support\PurchasingInventory\PurchasePaymentSheetRepository;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class PurchasePaymentSheetWorksheet extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'purchasing-inventory/purchase-payment-sheet/{sheetId}';

    protected static bool $shouldRegisterNavigation = false;

    public string $sheetId = '';

    /** @var array<string, mixed> */
    public array $sheet = [];

    /** @var list<array<string, mixed>> */
    public array $vendorLines = [];

    /** @var list<array<string, mixed>> */
    public array $paymentSources = [];

    public string $sheetTitle = '';

    public string $sheetDate = '';

    public string $purchaserName = '';

    public string $notes = '';

    public string $quickSupplierSearch = '';

    /** @var list<array<string, mixed>> */
    public array $quickSupplierResults = [];

    public static function moduleKey(): string
    {
        return 'purchasing-inventory';
    }

    public static function submenuKey(): string
    {
        return 'purchase-payment-sheet';
    }

    public function mount(string $sheetId): void
    {
        $sheet = app(PurchasePaymentSheetRepository::class)->find($sheetId);

        abort_if($sheet === null, 404);

        $this->sheetId = $sheetId;
        $this->hydrateFromSheet($sheet);
    }

    public function getTitle(): string | Htmlable
    {
        return (string) ($this->sheet['sheet_number'] ?? 'Purchase Payment Sheet');
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.purchasing-inventory.purchase-payment-sheet-worksheet')
                ->viewData(fn (): array => $this->worksheetViewData()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function worksheetViewData(): array
    {
        $builder = app(PurchasePaymentSheetBuilder::class);
        $vendorTotal = $builder->vendorPaymentsTotal($this->vendorLines);
        $sourceTotal = $builder->paymentSourcesTotal($this->paymentSources);
        $filledVendors = $builder->filledVendorLines($this->vendorLines);
        $filledSources = $builder->filledPaymentSources($this->paymentSources);
        $blankPrintRows = $builder->printBlankVendorRows();

        $printVendorLines = $filledVendors;

        for ($i = 0; $i < $blankPrintRows; $i++) {
            $printVendorLines[] = $builder->emptyVendorLine(count($printVendorLines) + 1);
        }

        return [
            'sheet' => $this->sheet,
            'sheetNumber' => (string) ($this->sheet['sheet_number'] ?? ''),
            'sheetTitle' => $this->sheetTitle,
            'sheetDate' => $this->sheetDate,
            'purchaserName' => $this->purchaserName,
            'notes' => $this->notes,
            'isNewSheet' => ($this->sheet['status'] ?? 'draft') === 'draft'
                && $builder->filledVendorCount($this->vendorLines) === 0,
            'vendorLines' => $this->vendorLines,
            'paymentSources' => $this->paymentSources,
            'printVendorLines' => $printVendorLines,
            'printPaymentSources' => $filledSources,
            'vendorCount' => count($filledVendors),
            'vendorTotal' => $builder->formatMoney($vendorTotal),
            'sourceCount' => count($filledSources),
            'sourceTotal' => $builder->formatMoney($sourceTotal),
            'balance' => $builder->formatMoney($sourceTotal - $vendorTotal),
            'quickSupplierSearch' => $this->quickSupplierSearch,
            'quickSupplierResults' => $this->quickSupplierResults,
        ];
    }

    public function updatedQuickSupplierSearch(): void
    {
        $term = trim($this->quickSupplierSearch);

        if (mb_strlen($term) < 2) {
            $this->quickSupplierResults = [];

            return;
        }

        $this->quickSupplierResults = app(PurchasePaymentSupplierSearch::class)->search($term);
    }

    public function addSupplierFromSearch(int $supplierId): void
    {
        $supplier = app(PurchasePaymentSupplierSearch::class)->findById($supplierId);

        if ($supplier === null) {
            return;
        }

        $index = $this->firstEmptyVendorRowIndex();

        if ($index === null) {
            if (! $this->appendVendorRow()) {
                return;
            }

            $index = $this->firstEmptyVendorRowIndex();
        }

        if ($index === null) {
            return;
        }

        $this->vendorLines[$index]['supplier_id'] = $supplier['id'];
        $this->vendorLines[$index]['vendor_name'] = $supplier['business_name'];
        $this->quickSupplierSearch = '';
        $this->quickSupplierResults = [];
        $this->syncVendorLines();
        $this->persistSheet(false);
    }

    public function addVendorRow(): void
    {
        if (! $this->appendVendorRow()) {
            return;
        }

        $this->syncVendorLines();
        $this->persistSheet(false);
    }

    public function removeVendorRow(int $index): void
    {
        if (! isset($this->vendorLines[$index])) {
            return;
        }

        $builder = app(PurchasePaymentSheetBuilder::class);

        if (count($this->vendorLines) <= $builder->defaultVendorRows()) {
            $this->vendorLines[$index] = $builder->emptyVendorLine($index + 1);
        } else {
            unset($this->vendorLines[$index]);
            $this->vendorLines = array_values($this->vendorLines);
        }

        $this->syncVendorLines();
        $this->persistSheet(false);
    }

    public function addPaymentSourceRow(): void
    {
        $builder = app(PurchasePaymentSheetBuilder::class);

        if (count($this->paymentSources) >= $builder->maxSourceRows()) {
            Notification::make()
                ->warning()
                ->title('Maximum payment source rows reached')
                ->send();

            return;
        }

        $this->paymentSources[] = $builder->emptyPaymentSourceLine();
        $this->syncPaymentSources();
        $this->persistSheet(false);
    }

    public function removePaymentSourceRow(int $index): void
    {
        if (! isset($this->paymentSources[$index])) {
            return;
        }

        $builder = app(PurchasePaymentSheetBuilder::class);

        if (count($this->paymentSources) <= $builder->defaultSourceRows()) {
            $this->paymentSources[$index] = $builder->emptyPaymentSourceLine();
        } else {
            unset($this->paymentSources[$index]);
            $this->paymentSources = array_values($this->paymentSources);
        }

        $this->syncPaymentSources();
        $this->persistSheet(false);
    }

    public function toggleVendorInvoiceOk(int $index): void
    {
        if (! isset($this->vendorLines[$index])) {
            return;
        }

        $this->vendorLines[$index]['invoice_ok'] = ! (bool) ($this->vendorLines[$index]['invoice_ok'] ?? false);

        if ($this->vendorLines[$index]['invoice_ok']) {
            $this->vendorLines[$index]['invoice_dispute'] = false;
        }

        $this->persistSheet(false);
    }

    public function toggleVendorInvoiceDispute(int $index): void
    {
        if (! isset($this->vendorLines[$index])) {
            return;
        }

        $this->vendorLines[$index]['invoice_dispute'] = ! (bool) ($this->vendorLines[$index]['invoice_dispute'] ?? false);

        if ($this->vendorLines[$index]['invoice_dispute']) {
            $this->vendorLines[$index]['invoice_ok'] = false;
        }

        $this->persistSheet(false);
    }

    public function updatedVendorLines(): void
    {
        $this->syncVendorLines();
        $this->persistSheet(false);
    }

    public function updatedPaymentSources(): void
    {
        $this->syncPaymentSources();
        $this->persistSheet(false);
    }

    public function updatedSheetDate(): void
    {
        $this->persistSheet(false);
    }

    public function updatedSheetTitle(): void
    {
        $this->persistSheet(false);
    }

    public function updatedPurchaserName(): void
    {
        $this->persistSheet(false);
    }

    public function updatedNotes(): void
    {
        $this->persistSheet(false);
    }

    public function saveSheet(): void
    {
        $builder = app(PurchasePaymentSheetBuilder::class);

        if ($builder->filledVendorCount($this->vendorLines) === 0) {
            Notification::make()
                ->warning()
                ->title('Add at least one vendor payment')
                ->send();

            return;
        }

        if (trim($this->purchaserName) === '') {
            Notification::make()
                ->warning()
                ->title('Enter purchaser name')
                ->body('A purchaser is required before saving this payment sheet.')
                ->send();

            return;
        }

        $this->sheet['status'] = 'saved';
        $this->persistSheet(false);

        Notification::make()
            ->success()
            ->title('Payment sheet saved')
            ->send();

        $this->redirect(PurchasePaymentSheet::getUrl());
    }

    public function discardSheet(): void
    {
        app(PurchasePaymentSheetRepository::class)->delete($this->sheetId);

        Notification::make()
            ->success()
            ->title('Payment sheet discarded')
            ->send();

        $this->redirect(PurchasePaymentSheet::getUrl());
    }

    public function printSheet(): void
    {
        $this->dispatch('agricart-pps-print');
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    protected function hydrateFromSheet(array $sheet): void
    {
        $builder = app(PurchasePaymentSheetBuilder::class);

        $this->sheet = $sheet;
        $this->sheetTitle = (string) ($sheet['title'] ?? '');
        $this->sheetDate = (string) ($sheet['sheet_date'] ?? now()->toDateString());
        $this->purchaserName = (string) ($sheet['purchaser_name'] ?? '');
        $this->notes = (string) ($sheet['notes'] ?? '');
        $this->vendorLines = $builder->normalizeVendorLines($sheet['vendor_lines'] ?? []);
        $this->paymentSources = $builder->normalizePaymentSources($sheet['payment_sources'] ?? []);
    }

    protected function persistSheet(bool $redirect = false): void
    {
        $this->syncVendorLines();
        $this->syncPaymentSources();

        $this->sheet['title'] = $this->sheetTitle;
        $this->sheet['sheet_date'] = $this->sheetDate;
        $this->sheet['purchaser_name'] = $this->purchaserName;
        $this->sheet['notes'] = $this->notes;
        $this->sheet['vendor_lines'] = $this->vendorLines;
        $this->sheet['payment_sources'] = $this->paymentSources;

        app(PurchasePaymentSheetRepository::class)->update($this->sheet);

        if ($redirect) {
            $this->redirect(PurchasePaymentSheet::getUrl());
        }
    }

    protected function syncVendorLines(): void
    {
        $this->vendorLines = app(PurchasePaymentSheetBuilder::class)->normalizeVendorLines($this->vendorLines);
    }

    protected function syncPaymentSources(): void
    {
        $this->paymentSources = app(PurchasePaymentSheetBuilder::class)->normalizePaymentSources($this->paymentSources);
    }

    protected function firstEmptyVendorRowIndex(): ?int
    {
        $builder = app(PurchasePaymentSheetBuilder::class);

        foreach ($this->vendorLines as $index => $line) {
            if (! $builder->vendorLineIsFilled($line)) {
                return $index;
            }
        }

        return null;
    }

    protected function appendVendorRow(): bool
    {
        $builder = app(PurchasePaymentSheetBuilder::class);

        if (count($this->vendorLines) >= $builder->maxVendorRows()) {
            Notification::make()
                ->warning()
                ->title('Maximum vendor rows reached')
                ->send();

            return false;
        }

        $this->vendorLines[] = $builder->emptyVendorLine(count($this->vendorLines) + 1);

        return true;
    }
}
