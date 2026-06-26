<?php

namespace App\Support\SalesPos;

use App\Models\SalesPos\SalesQuotation;
use App\Services\PurchasingInventory\DocumentNumberService;
use App\Services\SalesPos\PosSaleLineBuilder;
use App\Services\Settings\PrintingSettingResolver;
use App\Support\PurchasingInventory\SyncsSheetLines;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesQuotationRepository
{
    use SyncsSheetLines;

    public function __construct(
        protected DocumentNumberService $documentNumbers,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return SalesQuotation::query()
            ->with('lines')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (SalesQuotation $quotation): array => $this->toArray($quotation))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function held(): array
    {
        return SalesQuotation::query()
            ->with('lines')
            ->where('status', SalesQuotation::STATUS_HELD)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (SalesQuotation $quotation): array => $this->toArray($quotation))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $quotationId): ?array
    {
        $quotation = SalesQuotation::query()->with('lines')->find($quotationId);

        return $quotation === null ? null : $this->toArray($quotation);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByQuotationNumber(string $quotationNumber): ?array
    {
        $quotationNumber = trim($quotationNumber);

        if ($quotationNumber === '') {
            return null;
        }

        $quotation = SalesQuotation::query()
            ->with('lines')
            ->where('quotation_number', $quotationNumber)
            ->first();

        return $quotation === null ? null : $this->toArray($quotation);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes = []): array
    {
        $defaultStoreKey = (string) config('purchasing-inventory.demo_default_store', 'main');

        $quotation = SalesQuotation::query()->create([
            'quotation_number' => $this->documentNumbers->next('sales_quotation'),
            'status' => SalesQuotation::STATUS_DRAFT,
            'quotation_date' => (string) ($attributes['quotation_date'] ?? now()->toDateString()),
            'name_lang' => (string) ($attributes['name_lang'] ?? 'both'),
            'customer_id' => $attributes['customer_id'] ?? null,
            'customer_name' => (string) ($attributes['customer_name'] ?? config('sales-pos.walk_in_customer_label', 'Walk-in Customer')),
            'customer_mobile' => $attributes['customer_mobile'] ?? null,
            'store_key' => (string) ($attributes['store_key'] ?? $defaultStoreKey),
            'store_name' => (string) ($attributes['store_name'] ?? config('purchasing-inventory.demo_stores.' . $defaultStoreKey, 'Main Store')),
            'subtotal' => 0,
            'total' => 0,
            'notes' => (string) ($attributes['notes'] ?? ''),
            'held_label' => null,
            'print_paper_size' => (string) ($attributes['print_paper_size']
                ?? app(PrintingSettingResolver::class)->posReceiptProfile()['profile'] ?? '80mm'),
            'print_controls' => (bool) ($attributes['print_controls'] ?? false),
            'created_by' => auth()->id(),
        ]);

        return $this->toArray($quotation->load('lines'));
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    public function update(array $sheet): void
    {
        DB::transaction(function () use ($sheet): void {
            $model = SalesQuotation::query()->with('lines')->find($sheet['id'] ?? null);

            if ($model === null || ! $model->isEditable()) {
                return;
            }

            $rows = array_values($sheet['rows'] ?? []);
            $subtotal = PosSaleLineBuilder::subtotal($rows);

            $model->update([
                'status' => (string) ($sheet['status'] ?? $model->status),
                'quotation_date' => (string) ($sheet['quotation_date'] ?? $model->quotation_date?->toDateString()),
                'name_lang' => (string) ($sheet['name_lang'] ?? $model->name_lang),
                'customer_id' => $sheet['customer_id'] ?? null,
                'customer_name' => (string) ($sheet['customer_name'] ?? ''),
                'customer_mobile' => $sheet['customer_mobile'] ?? null,
                'store_key' => (string) ($sheet['store_key'] ?? $model->store_key),
                'store_name' => (string) ($sheet['store_name'] ?? $model->store_name),
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'notes' => (string) ($sheet['notes'] ?? ''),
                'held_label' => $sheet['held_label'] ?? null,
                'print_paper_size' => (string) ($sheet['print_paper_size'] ?? $model->print_paper_size),
                'print_controls' => (bool) ($sheet['print_controls'] ?? $model->print_controls),
            ]);

            $this->syncLineRows($model, $model->lines(), $rows);
        });
    }

    /**
     * @param  array<string, mixed>  $sheet
     * @return array<string, mixed>
     */
    public function finalize(array $sheet): array
    {
        return DB::transaction(function () use ($sheet): array {
            $model = SalesQuotation::query()->with('lines')->lockForUpdate()->find($sheet['id'] ?? null);

            if ($model === null) {
                throw ValidationException::withMessages(['quotation' => 'Quotation not found.']);
            }

            if ($model->status === SalesQuotation::STATUS_FINALIZED) {
                return $this->toArray($model);
            }

            if (! $model->isEditable()) {
                throw ValidationException::withMessages(['quotation' => 'This quotation can no longer be finalized.']);
            }

            $rows = array_values($sheet['rows'] ?? []);

            if ($rows === []) {
                throw ValidationException::withMessages(['rows' => 'Add at least one product line before finalizing the quotation.']);
            }

            $sheet['status'] = SalesQuotation::STATUS_FINALIZED;
            $this->update($sheet);

            $model->refresh()->load('lines');

            if ($model->finalized_at === null) {
                $model->update(['finalized_at' => now()]);
            }

            return $this->toArray($model->fresh('lines'));
        });
    }

    public function delete(string $quotationId): void
    {
        $quotation = SalesQuotation::query()->find($quotationId);

        if ($quotation === null || $quotation->status === SalesQuotation::STATUS_FINALIZED) {
            return;
        }

        $quotation->delete();
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(SalesQuotation $quotation): array
    {
        return [
            'id' => (string) $quotation->id,
            'quotation_number' => (string) $quotation->quotation_number,
            'status' => (string) $quotation->status,
            'quotation_date' => $quotation->quotation_date?->toDateString() ?? '',
            'name_lang' => (string) $quotation->name_lang,
            'customer_id' => $quotation->customer_id,
            'customer_name' => (string) ($quotation->customer_name ?? ''),
            'customer_mobile' => (string) ($quotation->customer_mobile ?? ''),
            'store_key' => (string) $quotation->store_key,
            'store_name' => (string) ($quotation->store_name ?? ''),
            'subtotal' => PosSaleLineBuilder::formatAmount((float) $quotation->subtotal),
            'total' => PosSaleLineBuilder::formatAmount((float) $quotation->total),
            'notes' => (string) ($quotation->notes ?? ''),
            'held_label' => $quotation->held_label,
            'print_paper_size' => (string) $quotation->print_paper_size,
            'print_controls' => (bool) $quotation->print_controls,
            'rows' => $this->rowsFromLines($quotation->lines),
            'finalized_at' => $quotation->finalized_at?->toIso8601String(),
            'created_at' => $quotation->created_at?->toIso8601String() ?? '',
            'updated_at' => $quotation->updated_at?->toIso8601String() ?? '',
        ];
    }
}
