<?php

namespace App\Support\PurchasingInventory;

use App\Models\PurchasingInventory\PurchasePaymentSheet;
use App\Services\PurchasingInventory\DocumentNumberService;
use App\Services\PurchasingInventory\PurchasePaymentSheetBuilder;
use Illuminate\Support\Facades\DB;

class PurchasePaymentSheetRepository
{
    public function __construct(
        protected DocumentNumberService $documentNumbers,
        protected PurchasePaymentSheetBuilder $builder,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return PurchasePaymentSheet::query()
            ->with('vendorLines')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PurchasePaymentSheet $sheet): array => $this->toArray($sheet))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $sheetId): ?array
    {
        $sheet = PurchasePaymentSheet::query()->with('vendorLines')->find($sheetId);

        return $sheet === null ? null : $this->toArray($sheet);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySheetNumber(string $sheetNumber): ?array
    {
        $needle = strtoupper(trim($sheetNumber));

        if ($needle === '') {
            return null;
        }

        $sheet = PurchasePaymentSheet::query()
            ->with('vendorLines')
            ->whereRaw('UPPER(sheet_number) = ?', [$needle])
            ->first();

        return $sheet === null ? null : $this->toArray($sheet);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes = []): array
    {
        $sheet = PurchasePaymentSheet::query()->create([
            'sheet_number' => $this->documentNumbers->next('purchase_payment'),
            'status' => 'draft',
            'title' => (string) ($attributes['title'] ?? ''),
            'sheet_date' => (string) ($attributes['sheet_date'] ?? now()->toDateString()),
            'purchaser_id' => $attributes['purchaser_id'] ?? null,
            'purchaser_name' => (string) ($attributes['purchaser_name'] ?? ''),
            'notes' => (string) ($attributes['notes'] ?? ''),
            'payment_sources' => $this->builder->blankPaymentSources(),
            'created_by' => auth()->id(),
        ]);

        $this->syncVendorLines($sheet, $this->builder->blankVendorLines());

        return $this->toArray($sheet->fresh('vendorLines'));
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    public function update(array $sheet): void
    {
        DB::transaction(function () use ($sheet): void {
            $model = PurchasePaymentSheet::query()->with('vendorLines')->find($sheet['id'] ?? null);

            if ($model === null) {
                return;
            }

            $model->update([
                'status' => (string) ($sheet['status'] ?? $model->status),
                'title' => (string) ($sheet['title'] ?? ''),
                'sheet_date' => (string) ($sheet['sheet_date'] ?? $model->sheet_date?->toDateString()),
                'purchaser_id' => $sheet['purchaser_id'] ?? null,
                'purchaser_name' => (string) ($sheet['purchaser_name'] ?? ''),
                'notes' => (string) ($sheet['notes'] ?? ''),
                'payment_sources' => array_values($sheet['payment_sources'] ?? []),
            ]);

            $this->syncVendorLines($model, array_values($sheet['vendor_lines'] ?? []));
        });
    }

    public function delete(string $sheetId): void
    {
        PurchasePaymentSheet::query()->whereKey($sheetId)->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $vendorLines
     */
    protected function syncVendorLines(PurchasePaymentSheet $sheet, array $vendorLines): void
    {
        $sheet->vendorLines()->delete();

        foreach ($vendorLines as $index => $line) {
            $supplierId = $line['supplier_id'] ?? null;
            $supplierId = is_numeric($supplierId) ? (int) $supplierId : null;

            $sheet->vendorLines()->create([
                'sort_order' => $index,
                'supplier_id' => $supplierId > 0 ? $supplierId : null,
                'payload' => $line,
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function vendorLinesFromModel(PurchasePaymentSheet $sheet): array
    {
        $lines = [];

        foreach ($sheet->vendorLines as $line) {
            $payload = $line->payload ?? [];

            if (! is_array($payload)) {
                $payload = [];
            }

            $lines[] = $payload;
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(PurchasePaymentSheet $sheet): array
    {
        return [
            'id' => (string) $sheet->id,
            'sheet_number' => (string) $sheet->sheet_number,
            'status' => (string) $sheet->status,
            'title' => (string) ($sheet->title ?? ''),
            'sheet_date' => $sheet->sheet_date?->toDateString() ?? '',
            'purchaser_id' => $sheet->purchaser_id,
            'purchaser_name' => (string) ($sheet->purchaser_name ?? ''),
            'notes' => (string) ($sheet->notes ?? ''),
            'vendor_lines' => $this->vendorLinesFromModel($sheet),
            'payment_sources' => array_values($sheet->payment_sources ?? []),
            'created_at' => $sheet->created_at?->toIso8601String() ?? '',
            'updated_at' => $sheet->updated_at?->toIso8601String() ?? '',
        ];
    }
}
