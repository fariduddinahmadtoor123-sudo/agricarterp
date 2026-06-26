<?php

namespace App\Support\SalesPos;

use App\Models\SalesPos\PosSale;
use App\Services\PurchasingInventory\DocumentNumberService;
use App\Services\PurchasingInventory\InventoryService;
use App\Services\SalesPos\PosSaleLineBuilder;
use App\Services\SalesPos\PosSaleReturnSummary;
use App\Services\Settings\PrintingSettingResolver;
use App\Support\PurchasingInventory\SyncsSheetLines;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosSaleRepository
{
    use SyncsSheetLines;

    public function __construct(
        protected DocumentNumberService $documentNumbers,
        protected InventoryService $inventory,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return PosSale::query()
            ->with('lines')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (PosSale $sale): array => $this->toArray($sale))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function held(): array
    {
        return PosSale::query()
            ->with('lines')
            ->where('status', PosSale::STATUS_HELD)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (PosSale $sale): array => $this->toArray($sale))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $saleId): ?array
    {
        $sale = PosSale::query()->with('lines')->find($saleId);

        return $sale === null ? null : $this->toArray($sale);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySaleNumber(string $saleNumber): ?array
    {
        $saleNumber = trim($saleNumber);

        if ($saleNumber === '') {
            return null;
        }

        $sale = PosSale::query()
            ->with('lines')
            ->where('sale_number', $saleNumber)
            ->first();

        return $sale === null ? null : $this->toArray($sale);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes = []): array
    {
        $defaultStoreKey = (string) config('purchasing-inventory.demo_default_store', 'main');

        $sale = PosSale::query()->create([
            'sale_number' => $this->documentNumbers->next('pos_sale'),
            'status' => PosSale::STATUS_DRAFT,
            'sale_date' => (string) ($attributes['sale_date'] ?? now()->toDateString()),
            'name_lang' => (string) ($attributes['name_lang'] ?? 'both'),
            'customer_id' => $attributes['customer_id'] ?? null,
            'customer_name' => (string) ($attributes['customer_name'] ?? config('sales-pos.walk_in_customer_label', 'Walk-in Customer')),
            'customer_mobile' => $attributes['customer_mobile'] ?? null,
            'store_key' => (string) ($attributes['store_key'] ?? $defaultStoreKey),
            'store_name' => (string) ($attributes['store_name'] ?? config('purchasing-inventory.demo_stores.' . $defaultStoreKey, 'Main Store')),
            'payment_method' => (string) ($attributes['payment_method'] ?? config('sales-pos.default_payment_method', 'cash')),
            'amount_paid' => 0,
            'subtotal' => 0,
            'total' => 0,
            'notes' => (string) ($attributes['notes'] ?? ''),
            'held_label' => null,
            'stock_applied' => false,
            'print_paper_size' => (string) ($attributes['print_paper_size']
                ?? app(PrintingSettingResolver::class)->posReceiptProfile()['profile'] ?? '80mm'),
            'print_controls' => (bool) ($attributes['print_controls'] ?? false),
            'created_by' => auth()->id(),
        ]);

        return $this->toArray($sale->load('lines'));
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    public function update(array $sheet): void
    {
        DB::transaction(function () use ($sheet): void {
            $model = PosSale::query()->with('lines')->find($sheet['id'] ?? null);

            if ($model === null || ! $model->isEditable()) {
                return;
            }

            $rows = array_values($sheet['rows'] ?? []);
            $subtotal = PosSaleLineBuilder::subtotal($rows);

            $model->update([
                'status' => (string) ($sheet['status'] ?? $model->status),
                'sale_date' => (string) ($sheet['sale_date'] ?? $model->sale_date?->toDateString()),
                'name_lang' => (string) ($sheet['name_lang'] ?? $model->name_lang),
                'customer_id' => $sheet['customer_id'] ?? null,
                'customer_name' => (string) ($sheet['customer_name'] ?? ''),
                'customer_mobile' => $sheet['customer_mobile'] ?? null,
                'store_key' => (string) ($sheet['store_key'] ?? $model->store_key),
                'store_name' => (string) ($sheet['store_name'] ?? $model->store_name),
                'payment_method' => (string) ($sheet['payment_method'] ?? $model->payment_method),
                'amount_paid' => PosSaleLineBuilder::numeric($sheet['amount_paid'] ?? $model->amount_paid),
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
    public function complete(array $sheet): array
    {
        return DB::transaction(function () use ($sheet): array {
            $model = PosSale::query()->with('lines')->lockForUpdate()->find($sheet['id'] ?? null);

            if ($model === null) {
                throw ValidationException::withMessages(['sale' => 'Sale not found.']);
            }

            if ($model->status === PosSale::STATUS_COMPLETED) {
                return $this->toArray($model);
            }

            if (! $model->isEditable()) {
                throw ValidationException::withMessages(['sale' => 'This sale can no longer be completed.']);
            }

            $rows = array_values($sheet['rows'] ?? []);

            if ($rows === []) {
                throw ValidationException::withMessages(['rows' => 'Add at least one product line before completing the sale.']);
            }

            $sheet['status'] = PosSale::STATUS_COMPLETED;
            $this->update($sheet);

            $model->refresh()->load('lines');

            if (! $model->stock_applied) {
                $this->inventory->issuePosSale(
                    (string) $model->id,
                    (string) $model->store_key,
                    $this->rowsFromLines($model->lines),
                );

                $model->update([
                    'stock_applied' => true,
                    'completed_at' => now(),
                ]);
            }

            return $this->toArray($model->fresh('lines'));
        });
    }

    public function delete(string $saleId): void
    {
        $sale = PosSale::query()->find($saleId);

        if ($sale === null || $sale->status === PosSale::STATUS_COMPLETED) {
            return;
        }

        $sale->delete();
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(PosSale $sale): array
    {
        $returnSummary = $sale->status === PosSale::STATUS_COMPLETED
            ? app(PosSaleReturnSummary::class)->forSale((string) $sale->id)
            : null;

        return [
            'id' => (string) $sale->id,
            'sale_number' => (string) $sale->sale_number,
            'status' => (string) $sale->status,
            'sale_date' => $sale->sale_date?->toDateString() ?? '',
            'name_lang' => (string) $sale->name_lang,
            'customer_id' => $sale->customer_id,
            'customer_name' => (string) ($sale->customer_name ?? ''),
            'customer_mobile' => (string) ($sale->customer_mobile ?? ''),
            'store_key' => (string) $sale->store_key,
            'store_name' => (string) ($sale->store_name ?? ''),
            'payment_method' => (string) $sale->payment_method,
            'amount_paid' => PosSaleLineBuilder::formatAmount((float) $sale->amount_paid),
            'subtotal' => PosSaleLineBuilder::formatAmount((float) $sale->subtotal),
            'total' => PosSaleLineBuilder::formatAmount((float) $sale->total),
            'return_total' => PosSaleLineBuilder::formatAmount((float) $sale->return_total),
            'refund_total' => PosSaleLineBuilder::formatAmount((float) $sale->refund_total),
            'credit_return_total' => PosSaleLineBuilder::formatAmount((float) $sale->credit_return_total),
            'net_total' => PosSaleLineBuilder::formatAmount(
                (float) ($sale->net_total > 0 ? $sale->net_total : max(0, (float) $sale->total - (float) $sale->return_total))
            ),
            'return_summary' => $returnSummary,
            'notes' => (string) ($sale->notes ?? ''),
            'held_label' => $sale->held_label,
            'stock_applied' => (bool) $sale->stock_applied,
            'print_paper_size' => (string) $sale->print_paper_size,
            'print_controls' => (bool) $sale->print_controls,
            'rows' => $this->rowsFromLines($sale->lines),
            'completed_at' => $sale->completed_at?->toIso8601String(),
            'created_at' => $sale->created_at?->toIso8601String() ?? '',
            'updated_at' => $sale->updated_at?->toIso8601String() ?? '',
        ];
    }
}
