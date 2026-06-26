<?php

namespace App\Filament\Pages\SalesPos;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Models\SalesPos\SalesReturn;
use App\Services\SalesPos\PosSaleLineBuilder;
use App\Services\SalesPos\PosSaleReturnLineBuilder;
use App\Services\Settings\CompanySettingResolver;
use App\Support\SalesPos\SalesReturnRepository;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class SalesReturnWorksheet extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'sales-pos/sales-returns/{returnId}';

    protected static bool $shouldRegisterNavigation = false;

    public string $returnId = '';

    /** @var array<string, mixed> */
    public array $sheet = [];

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public string $nameLang = 'both';

    public string $returnDate = '';

    public string $notes = '';

    public string $refundNotes = '';

    public string $refundMethod = 'cash';

    public string $refundStatus = 'pending';

    public string $refundAmount = '';

    public string $creditAmount = '';

    public string $loadSaleNumber = '';

    public string $printPaperSize = '80mm';

    public function mount(string $returnId): void
    {
        $sheet = app(SalesReturnRepository::class)->find($returnId);

        abort_if($sheet === null, 404);

        $this->returnId = $returnId;
        $this->hydrateFromSheet($sheet);
    }

    public function getTitle(): string | Htmlable
    {
        return (string) ($this->sheet['return_number'] ?? 'Sales Return');
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public static function moduleKey(): string
    {
        return 'sales-pos';
    }

    public static function submenuKey(): string
    {
        return 'sales-returns';
    }

    public function content(Schema $schema): Schema
    {
        $company = app(CompanySettingResolver::class);
        $logoUrl = $company->logoUrl();

        return $schema->components([
            View::make('filament.sales-pos.sales-return-worksheet')
                ->viewData(fn (): array => [
                    'returnNumber' => (string) ($this->sheet['return_number'] ?? ''),
                    'sheet' => $this->sheet,
                    'isEditable' => $this->isEditable(),
                    'returnDate' => $this->returnDate,
                    'nameLang' => $this->nameLang,
                    'notes' => $this->notes,
                    'refundNotes' => $this->refundNotes,
                    'rows' => $this->isEditable() ? $this->rows : $this->printableRows(),
                    'itemCount' => count(array_filter(
                        $this->rows,
                        fn (array $row): bool => PosSaleLineBuilder::numeric($row['return_qty'] ?? '') > 0,
                    )),
                    'loadSaleNumber' => $this->loadSaleNumber,
                    'saleLoaded' => filled($this->sheet['pos_sale_id'] ?? null),
                    'saleHistory' => filled($this->sheet['pos_sale_id'] ?? null)
                        ? app(SalesReturnRepository::class)->forSale((string) $this->sheet['pos_sale_id'])
                        : [],
                    'refundMethods' => config('sales-pos.return_refund_methods', []),
                    'refundStatuses' => config('sales-pos.return_refund_statuses', []),
                    'paymentMethods' => config('sales-pos.payment_methods', []),
                    'refundMethod' => $this->refundMethod,
                    'refundStatus' => $this->refundStatus,
                    'refundAmount' => $this->refundAmount,
                    'creditAmount' => $this->creditAmount,
                    'total' => PosSaleLineBuilder::formatAmount(PosSaleReturnLineBuilder::subtotal($this->rows)),
                    'currencyCode' => $company->currency(),
                    'printPaperSizes' => config('sales-pos.print_paper_sizes', []),
                    'printPaperSize' => $this->printPaperSize,
                    'companyNameEn' => $company->nameEn(),
                    'companyNameUr' => $company->nameUr(),
                    'companyLogoUrl' => $this->absoluteUrl($logoUrl),
                    'companyPhones' => $company->phones(),
                    'companyEmails' => $company->emails(),
                    'companyAddressEn' => $company->addressEn(),
                    'companyAddressUr' => $company->addressUr(),
                    'companyWebsiteUrl' => $company->websiteUrl(),
                ]),
        ]);
    }

    public function loadSale(): void
    {
        if (! $this->isEditable()) {
            return;
        }

        try {
            $sheet = app(SalesReturnRepository::class)->loadFromSaleNumber($this->returnId, $this->loadSaleNumber);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Could not load sale')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Validation failed.')
                ->send();

            return;
        }

        $this->hydrateFromSheet($sheet);
        $this->loadSaleNumber = '';

        Notification::make()
            ->success()
            ->title('Sale loaded')
            ->body((string) ($sheet['sale_number'] ?? ''))
            ->send();
    }

    public function updatedRows(): void
    {
        $this->rows = array_map(
            fn (array $row): array => PosSaleReturnLineBuilder::recalculate($row),
            $this->rows,
        );
        $this->syncRefundAmounts();
        $this->persistSheet(false);
    }

    public function updatedRefundMethod(): void
    {
        $this->syncRefundAmounts();
        $this->persistSheet(false);
    }

    public function updatedReturnDate(): void
    {
        $this->sheet['return_date'] = $this->returnDate;
        $this->persistSheet(false);
    }

    public function updatedNotes(): void
    {
        $this->sheet['notes'] = trim($this->notes);
        $this->persistSheet(false);
    }

    public function updatedRefundNotes(): void
    {
        $this->sheet['refund_notes'] = trim($this->refundNotes);
        $this->persistSheet(false);
    }

    public function updatedRefundAmount(): void
    {
        $this->persistSheet(false);
    }

    public function updatedRefundStatus(): void
    {
        $this->persistSheet(false);
    }

    public function setNameLang(string $lang): void
    {
        if (! $this->isEditable()) {
            return;
        }

        $this->nameLang = $lang;
        $this->sheet['name_lang'] = $lang;
        $this->persistSheet(false);
    }

    public function updatedPrintPaperSize(): void
    {
        // Screen-only preference; not persisted on return sheets.
    }

    public function fillReturnTotal(): void
    {
        $total = PosSaleReturnLineBuilder::subtotal($this->rows);
        $this->refundAmount = PosSaleLineBuilder::formatAmount($total);
        $this->persistSheet(false);
    }

    public function completeReturn(): void
    {
        if (! $this->isEditable()) {
            return;
        }

        try {
            $completed = app(SalesReturnRepository::class)->complete($this->buildSheetPayload());
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Could not complete return')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Validation failed.')
                ->send();

            return;
        }

        $this->hydrateFromSheet($completed);

        Notification::make()
            ->success()
            ->title('Return completed')
            ->body('Stock restored. Refund/credit recorded.')
            ->send();
    }

    public function startNewReturn(): void
    {
        $sheet = app(SalesReturnRepository::class)->create();

        $this->redirect(static::getUrl(['returnId' => $sheet['id']]));
    }

    public function discardReturn(): void
    {
        if (($this->sheet['status'] ?? '') === SalesReturn::STATUS_COMPLETED) {
            return;
        }

        app(SalesReturnRepository::class)->delete($this->returnId);

        Notification::make()->success()->title('Return discarded')->send();

        $this->redirect(SalesReturns::getUrl());
    }

    protected function isEditable(): bool
    {
        return ($this->sheet['status'] ?? '') === SalesReturn::STATUS_DRAFT;
    }

    protected function syncRefundAmounts(): void
    {
        $total = PosSaleReturnLineBuilder::subtotal($this->rows);

        if ($this->refundMethod === 'customer_credit') {
            $this->creditAmount = PosSaleLineBuilder::formatAmount($total);
            $this->refundAmount = '';
            $this->refundStatus = SalesReturn::REFUND_CREDITED;
        } elseif ($this->refundMethod === 'original_payment' && ($this->sheet['original_payment_method'] ?? '') === 'credit') {
            $this->creditAmount = PosSaleLineBuilder::formatAmount($total);
            $this->refundAmount = '';
            $this->refundStatus = SalesReturn::REFUND_CREDITED;
        } else {
            $this->creditAmount = '';

            if (PosSaleLineBuilder::numeric($this->refundAmount) <= 0 && $total > 0) {
                $this->refundAmount = PosSaleLineBuilder::formatAmount($total);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    protected function hydrateFromSheet(array $sheet): void
    {
        $this->sheet = $sheet;
        $this->rows = array_values($sheet['rows'] ?? []);
        $this->nameLang = (string) ($sheet['name_lang'] ?? 'both');
        $this->returnDate = (string) ($sheet['return_date'] ?? now()->toDateString());
        $this->notes = (string) ($sheet['notes'] ?? '');
        $this->refundNotes = (string) ($sheet['refund_notes'] ?? '');
        $this->refundMethod = (string) ($sheet['refund_method'] ?? 'cash');
        $this->refundStatus = (string) ($sheet['refund_status'] ?? SalesReturn::REFUND_PENDING);
        $this->refundAmount = (string) ($sheet['refund_amount'] ?? '');
        $this->creditAmount = (string) ($sheet['credit_amount'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSheetPayload(): array
    {
        $this->syncRefundAmounts();

        return [
            ...$this->sheet,
            'return_date' => $this->returnDate,
            'notes' => trim($this->notes),
            'refund_notes' => trim($this->refundNotes),
            'refund_method' => $this->refundMethod,
            'refund_status' => $this->refundStatus,
            'refund_amount' => $this->refundAmount,
            'credit_amount' => $this->creditAmount,
            'rows' => $this->rows,
        ];
    }

    protected function persistSheet(bool $notify = false): void
    {
        if (! $this->isEditable()) {
            return;
        }

        $this->syncRefundAmounts();

        $this->sheet = [
            ...$this->sheet,
            'return_date' => $this->returnDate,
            'notes' => trim($this->notes),
            'refund_notes' => trim($this->refundNotes),
            'refund_method' => $this->refundMethod,
            'refund_status' => $this->refundStatus,
            'refund_amount' => $this->refundAmount,
            'credit_amount' => $this->creditAmount,
            'rows' => $this->rows,
        ];

        app(SalesReturnRepository::class)->update($this->sheet);

        if ($notify) {
            Notification::make()->success()->title('Return saved')->send();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function printableRows(): array
    {
        if ($this->isEditable()) {
            return $this->rows;
        }

        return array_values(array_filter(
            $this->rows,
            fn (array $row): bool => PosSaleLineBuilder::numeric($row['return_qty'] ?? '') > 0,
        ));
    }

    protected function absoluteUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        return str_starts_with($url, 'http') ? $url : url($url);
    }
}
