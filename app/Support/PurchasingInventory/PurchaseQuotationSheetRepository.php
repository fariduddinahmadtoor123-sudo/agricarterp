<?php

namespace App\Support\PurchasingInventory;

use App\Models\PurchasingInventory\PurchaseQuotationSheet;
use App\Services\PurchasingInventory\DocumentNumberService;
use Illuminate\Support\Facades\DB;

class PurchaseQuotationSheetRepository
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
        return PurchaseQuotationSheet::query()
            ->with('lines')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PurchaseQuotationSheet $sheet): array => $this->toArray($sheet))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $sheetId): ?array
    {
        $sheet = PurchaseQuotationSheet::query()->with('lines')->find($sheetId);

        return $sheet === null ? null : $this->toArray($sheet);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByQuotationNumber(string $quotationNumber): ?array
    {
        $needle = strtoupper(trim($quotationNumber));

        if ($needle === '') {
            return null;
        }

        $sheet = PurchaseQuotationSheet::query()
            ->with('lines')
            ->whereRaw('UPPER(quotation_number) = ?', [$needle])
            ->first();

        return $sheet === null ? null : $this->toArray($sheet);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes = []): array
    {
        $defaultStoreKey = (string) config('purchasing-inventory.demo_default_store', 'main');

        $sheet = PurchaseQuotationSheet::query()->create([
            'quotation_number' => $this->documentNumbers->next('purchase_quotation'),
            'status' => 'draft',
            'title' => (string) ($attributes['title'] ?? ''),
            'sheet_date' => (string) ($attributes['sheet_date'] ?? now()->toDateString()),
            'name_lang' => (string) ($attributes['name_lang'] ?? 'both'),
            'notes' => (string) ($attributes['notes'] ?? ''),
            'supplier_id' => $attributes['supplier_id'] ?? null,
            'supplier_name' => (string) ($attributes['supplier_name'] ?? ''),
            'store_key' => (string) ($attributes['store_key'] ?? $defaultStoreKey),
            'store_name' => (string) ($attributes['store_name'] ?? config('purchasing-inventory.demo_stores.' . $defaultStoreKey, 'Main Store (Demo)')),
            'created_by' => auth()->id(),
        ]);

        return $this->toArray($sheet->load('lines'));
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    public function update(array $sheet): void
    {
        DB::transaction(function () use ($sheet): void {
            $model = PurchaseQuotationSheet::query()->with('lines')->find($sheet['id'] ?? null);

            if ($model === null) {
                return;
            }

            $model->update([
                'status' => (string) ($sheet['status'] ?? $model->status),
                'title' => (string) ($sheet['title'] ?? ''),
                'sheet_date' => (string) ($sheet['sheet_date'] ?? $model->sheet_date?->toDateString()),
                'name_lang' => (string) ($sheet['name_lang'] ?? $model->name_lang),
                'notes' => (string) ($sheet['notes'] ?? ''),
                'supplier_id' => $sheet['supplier_id'] ?? null,
                'supplier_name' => (string) ($sheet['supplier_name'] ?? ''),
                'store_key' => (string) ($sheet['store_key'] ?? $model->store_key),
                'store_name' => (string) ($sheet['store_name'] ?? $model->store_name),
            ]);

            $this->syncLineRows($model, $model->lines(), array_values($sheet['rows'] ?? []));
        });
    }

    public function delete(string $sheetId): void
    {
        PurchaseQuotationSheet::query()->whereKey($sheetId)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(PurchaseQuotationSheet $sheet): array
    {
        return [
            'id' => (string) $sheet->id,
            'quotation_number' => (string) $sheet->quotation_number,
            'status' => (string) $sheet->status,
            'title' => (string) ($sheet->title ?? ''),
            'sheet_date' => $sheet->sheet_date?->toDateString() ?? '',
            'name_lang' => (string) $sheet->name_lang,
            'notes' => (string) ($sheet->notes ?? ''),
            'supplier_id' => $sheet->supplier_id,
            'supplier_name' => (string) ($sheet->supplier_name ?? ''),
            'store_key' => (string) $sheet->store_key,
            'store_name' => (string) ($sheet->store_name ?? ''),
            'rows' => $this->rowsFromLines($sheet->lines),
            'created_at' => $sheet->created_at?->toIso8601String() ?? '',
            'updated_at' => $sheet->updated_at?->toIso8601String() ?? '',
        ];
    }
}
