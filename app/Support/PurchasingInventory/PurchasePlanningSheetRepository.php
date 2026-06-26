<?php

namespace App\Support\PurchasingInventory;

use App\Models\PurchasingInventory\PurchasePlanningSheet;
use App\Services\PurchasingInventory\DocumentNumberService;
use Illuminate\Support\Facades\DB;

class PurchasePlanningSheetRepository
{
    use EnforcesPurchasingInventoryPermissions;
    use SyncsSheetLines;

    public function __construct(
        protected DocumentNumberService $documentNumbers,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return PurchasePlanningSheet::query()
            ->with('lines')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PurchasePlanningSheet $sheet): array => $this->toArray($sheet))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $sheetId): ?array
    {
        $sheet = PurchasePlanningSheet::query()->with('lines')->find($sheetId);

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

        $sheet = PurchasePlanningSheet::query()
            ->with('lines')
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
        $this->authorizePurchasingCreate();

        $sheet = PurchasePlanningSheet::query()->create([
            'sheet_number' => $this->documentNumbers->next('purchase_planning'),
            'status' => 'draft',
            'title' => (string) ($attributes['title'] ?? ''),
            'sheet_date' => (string) ($attributes['sheet_date'] ?? now()->toDateString()),
            'name_lang' => (string) ($attributes['name_lang'] ?? 'both'),
            'notes' => (string) ($attributes['notes'] ?? ''),
            'created_by' => auth()->id(),
        ]);

        return $this->toArray($sheet->load('lines'));
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    public function update(array $sheet): void
    {
        $this->authorizePurchasingEdit();

        DB::transaction(function () use ($sheet): void {
            $model = PurchasePlanningSheet::query()->with('lines')->find($sheet['id'] ?? null);

            if ($model === null) {
                return;
            }

            $model->update([
                'status' => (string) ($sheet['status'] ?? $model->status),
                'title' => (string) ($sheet['title'] ?? ''),
                'sheet_date' => (string) ($sheet['sheet_date'] ?? $model->sheet_date?->toDateString()),
                'name_lang' => (string) ($sheet['name_lang'] ?? $model->name_lang),
                'notes' => (string) ($sheet['notes'] ?? ''),
            ]);

            $this->syncLineRows($model, $model->lines(), array_values($sheet['rows'] ?? []));
        });
    }

    public function delete(string $sheetId): void
    {
        $this->authorizePurchasingDelete();

        PurchasePlanningSheet::query()->whereKey($sheetId)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(PurchasePlanningSheet $sheet): array
    {
        return [
            'id' => (string) $sheet->id,
            'sheet_number' => (string) $sheet->sheet_number,
            'status' => (string) $sheet->status,
            'title' => (string) ($sheet->title ?? ''),
            'sheet_date' => $sheet->sheet_date?->toDateString() ?? '',
            'name_lang' => (string) $sheet->name_lang,
            'notes' => (string) ($sheet->notes ?? ''),
            'rows' => $this->rowsFromLines($sheet->lines),
            'created_at' => $sheet->created_at?->toIso8601String() ?? '',
            'updated_at' => $sheet->updated_at?->toIso8601String() ?? '',
        ];
    }
}
