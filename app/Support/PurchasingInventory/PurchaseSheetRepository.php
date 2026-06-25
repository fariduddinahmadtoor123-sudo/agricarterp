<?php

namespace App\Support\PurchasingInventory;

use App\Models\PurchasingInventory\PurchaseSheet;
use App\Services\PurchasingInventory\DocumentNumberService;
use App\Services\Settings\PrintingSettingResolver;
use Illuminate\Support\Facades\DB;

class PurchaseSheetRepository
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
        return PurchaseSheet::query()
            ->with('lines')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PurchaseSheet $sheet): array => $this->toArray($sheet))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $sheetId): ?array
    {
        $sheet = PurchaseSheet::query()->with('lines')->find($sheetId);

        return $sheet === null ? null : $this->toArray($sheet);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByPurchaseNumber(string $purchaseNumber): ?array
    {
        $needle = strtoupper(trim($purchaseNumber));

        if ($needle === '') {
            return null;
        }

        $sheet = PurchaseSheet::query()
            ->with('lines')
            ->whereRaw('UPPER(purchase_number) = ?', [$needle])
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

        $sheet = PurchaseSheet::query()->create([
            'purchase_number' => $this->documentNumbers->next('purchase'),
            'status' => 'draft',
            'title' => (string) ($attributes['title'] ?? ''),
            'sheet_date' => (string) ($attributes['sheet_date'] ?? now()->toDateString()),
            'name_lang' => (string) ($attributes['name_lang'] ?? 'both'),
            'notes' => (string) ($attributes['notes'] ?? ''),
            'supplier_id' => $attributes['supplier_id'] ?? null,
            'supplier_name' => (string) ($attributes['supplier_name'] ?? ''),
            'store_key' => (string) ($attributes['store_key'] ?? $defaultStoreKey),
            'store_name' => (string) ($attributes['store_name'] ?? config('purchasing-inventory.demo_stores.' . $defaultStoreKey, 'Main Store (Demo)')),
            'invoice_payment_status' => (string) ($attributes['invoice_payment_status'] ?? 'unpaid'),
            'goods_receipt_status' => (string) ($attributes['goods_receipt_status'] ?? 'pending'),
            'dispute_status' => (string) ($attributes['dispute_status'] ?? 'none'),
            'dispute_notes' => (string) ($attributes['dispute_notes'] ?? ''),
            'invoice_image_path' => null,
            'linked_planning_id' => null,
            'linked_planning_number' => '',
            'linked_quotation_id' => null,
            'linked_quotation_number' => '',
            'payment_amount' => '',
            'payment_notes' => '',
            'print_paper_size' => (string) ($attributes['print_paper_size']
                ?? app(PrintingSettingResolver::class)->purchaseSheetPaperKey()),
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
            $model = PurchaseSheet::query()->with('lines')->find($sheet['id'] ?? null);

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
                'invoice_payment_status' => (string) ($sheet['invoice_payment_status'] ?? $model->invoice_payment_status),
                'goods_receipt_status' => (string) ($sheet['goods_receipt_status'] ?? $model->goods_receipt_status),
                'dispute_status' => (string) ($sheet['dispute_status'] ?? $model->dispute_status),
                'dispute_notes' => (string) ($sheet['dispute_notes'] ?? ''),
                'invoice_image_path' => $sheet['invoice_image_path'] ?? null,
                'linked_planning_id' => $sheet['linked_planning_id'] ?? null,
                'linked_planning_number' => (string) ($sheet['linked_planning_number'] ?? ''),
                'linked_quotation_id' => $sheet['linked_quotation_id'] ?? null,
                'linked_quotation_number' => (string) ($sheet['linked_quotation_number'] ?? ''),
                'payment_amount' => (string) ($sheet['payment_amount'] ?? ''),
                'payment_notes' => (string) ($sheet['payment_notes'] ?? ''),
                'print_paper_size' => (string) ($sheet['print_paper_size'] ?? $model->print_paper_size),
            ]);

            $this->syncLineRows($model, $model->lines(), array_values($sheet['rows'] ?? []));
        });
    }

    public function delete(string $sheetId): void
    {
        PurchaseSheet::query()->whereKey($sheetId)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(PurchaseSheet $sheet): array
    {
        return [
            'id' => (string) $sheet->id,
            'purchase_number' => (string) $sheet->purchase_number,
            'status' => (string) $sheet->status,
            'title' => (string) ($sheet->title ?? ''),
            'sheet_date' => $sheet->sheet_date?->toDateString() ?? '',
            'name_lang' => (string) $sheet->name_lang,
            'notes' => (string) ($sheet->notes ?? ''),
            'supplier_id' => $sheet->supplier_id,
            'supplier_name' => (string) ($sheet->supplier_name ?? ''),
            'store_key' => (string) $sheet->store_key,
            'store_name' => (string) ($sheet->store_name ?? ''),
            'invoice_payment_status' => (string) $sheet->invoice_payment_status,
            'goods_receipt_status' => (string) $sheet->goods_receipt_status,
            'dispute_status' => (string) $sheet->dispute_status,
            'dispute_notes' => (string) ($sheet->dispute_notes ?? ''),
            'invoice_image_path' => $sheet->invoice_image_path,
            'linked_planning_id' => $sheet->linked_planning_id,
            'linked_planning_number' => (string) ($sheet->linked_planning_number ?? ''),
            'linked_quotation_id' => $sheet->linked_quotation_id,
            'linked_quotation_number' => (string) ($sheet->linked_quotation_number ?? ''),
            'payment_amount' => (string) ($sheet->payment_amount ?? ''),
            'payment_notes' => (string) ($sheet->payment_notes ?? ''),
            'print_paper_size' => (string) $sheet->print_paper_size,
            'rows' => $this->rowsFromLines($sheet->lines),
            'created_at' => $sheet->created_at?->toIso8601String() ?? '',
            'updated_at' => $sheet->updated_at?->toIso8601String() ?? '',
        ];
    }
}
