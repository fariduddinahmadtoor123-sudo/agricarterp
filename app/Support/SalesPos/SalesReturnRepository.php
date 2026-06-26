<?php

namespace App\Support\SalesPos;

use App\Models\SalesPos\PosSale;
use App\Models\SalesPos\SalesReturn;
use App\Services\PurchasingInventory\DocumentNumberService;
use App\Services\PurchasingInventory\InventoryService;
use App\Services\SalesPos\CustomerAccountService;
use App\Services\SalesPos\PosSaleLineBuilder;
use App\Services\SalesPos\PosSaleReturnLineBuilder;
use App\Services\SalesPos\PosSaleReturnSummary;
use App\Support\PurchasingInventory\SyncsSheetLines;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnRepository
{
    use SyncsSheetLines;

    public function __construct(
        protected DocumentNumberService $documentNumbers,
        protected InventoryService $inventory,
        protected PosSaleRepository $posSales,
        protected CustomerAccountService $customerAccounts,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return SalesReturn::query()
            ->with('lines')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (SalesReturn $return): array => $this->toArray($return))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forSale(string $posSaleId): array
    {
        return SalesReturn::query()
            ->with('lines')
            ->where('pos_sale_id', $posSaleId)
            ->where('status', SalesReturn::STATUS_COMPLETED)
            ->orderByDesc('completed_at')
            ->get()
            ->map(fn (SalesReturn $return): array => $this->toArray($return))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $returnId): ?array
    {
        $return = SalesReturn::query()->with('lines')->find($returnId);

        return $return === null ? null : $this->toArray($return);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByReturnNumber(string $returnNumber): ?array
    {
        $returnNumber = trim($returnNumber);

        if ($returnNumber === '') {
            return null;
        }

        $return = SalesReturn::query()
            ->with('lines')
            ->where('return_number', $returnNumber)
            ->first();

        return $return === null ? null : $this->toArray($return);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(): array
    {
        $defaultStoreKey = (string) config('purchasing-inventory.demo_default_store', 'main');

        $return = SalesReturn::query()->create([
            'return_number' => $this->documentNumbers->next('sales_return'),
            'status' => SalesReturn::STATUS_DRAFT,
            'return_date' => now()->toDateString(),
            'store_key' => $defaultStoreKey,
            'store_name' => (string) config('purchasing-inventory.demo_stores.' . $defaultStoreKey, 'Main Store'),
            'refund_method' => 'cash',
            'refund_status' => SalesReturn::REFUND_PENDING,
            'created_by' => auth()->id(),
        ]);

        return $this->toArray($return->load('lines'));
    }

    /**
     * @return array<string, mixed>
     */
    public function loadFromSaleNumber(string $returnId, string $saleNumber): array
    {
        $sale = $this->posSales->findBySaleNumber(trim($saleNumber));

        if ($sale === null) {
            throw ValidationException::withMessages(['sale_number' => 'POS sale not found.']);
        }

        if (($sale['status'] ?? '') !== PosSale::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['sale_number' => 'Only completed sales can be returned.']);
        }

        return $this->attachSale($returnId, $sale);
    }

    /**
     * @param  array<string, mixed>  $sale
     * @return array<string, mixed>
     */
    public function attachSale(string $returnId, array $sale): array
    {
        $model = SalesReturn::query()->with('lines')->find($returnId);

        if ($model === null || ! $model->isEditable()) {
            throw ValidationException::withMessages(['return' => 'Return is not editable.']);
        }

        $returnedByLine = PosSaleReturnLineBuilder::returnedQtyBySourceLine((string) $sale['id'], $returnId);
        $rows = [];

        foreach ($sale['rows'] ?? [] as $saleLine) {
            $sourceId = (string) ($saleLine['line_id'] ?? '');
            $previouslyReturned = $returnedByLine[$sourceId] ?? 0.0;
            $row = PosSaleReturnLineBuilder::fromSaleLine($saleLine, $previouslyReturned);

            if (PosSaleLineBuilder::numeric($row['returnable_qty'] ?? '') > 0) {
                $rows[] = $row;
            }
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['sale_number' => 'All items on this sale have already been fully returned.']);
        }

        $paymentMethod = (string) ($sale['payment_method'] ?? 'cash');
        $defaultRefund = $paymentMethod === 'credit' ? 'customer_credit' : 'cash';

        $model->update([
            'pos_sale_id' => $sale['id'],
            'sale_number' => (string) ($sale['sale_number'] ?? ''),
            'name_lang' => (string) ($sale['name_lang'] ?? 'both'),
            'customer_id' => $sale['customer_id'] ?? null,
            'customer_name' => (string) ($sale['customer_name'] ?? ''),
            'customer_mobile' => $sale['customer_mobile'] ?? null,
            'store_key' => (string) ($sale['store_key'] ?? $model->store_key),
            'store_name' => (string) ($sale['store_name'] ?? $model->store_name),
            'original_payment_method' => $paymentMethod,
            'refund_method' => $defaultRefund,
        ]);

        $this->syncLineRows($model, $model->lines(), $rows);

        return $this->toArray($model->fresh('lines'));
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    public function update(array $sheet): void
    {
        DB::transaction(function () use ($sheet): void {
            $model = SalesReturn::query()->with('lines')->find($sheet['id'] ?? null);

            if ($model === null || ! $model->isEditable()) {
                return;
            }

            $rows = array_values(array_filter(
                $sheet['rows'] ?? [],
                fn (array $row): bool => PosSaleLineBuilder::numeric($row['return_qty'] ?? $row['qty'] ?? '') > 0,
            ));

            $subtotal = PosSaleReturnLineBuilder::subtotal($rows);

            $model->update([
                'return_date' => (string) ($sheet['return_date'] ?? $model->return_date?->toDateString()),
                'name_lang' => (string) ($sheet['name_lang'] ?? $model->name_lang),
                'refund_method' => (string) ($sheet['refund_method'] ?? $model->refund_method),
                'refund_status' => (string) ($sheet['refund_status'] ?? $model->refund_status),
                'return_subtotal' => $subtotal,
                'return_total' => $subtotal,
                'refund_amount' => PosSaleLineBuilder::numeric($sheet['refund_amount'] ?? 0),
                'credit_amount' => PosSaleLineBuilder::numeric($sheet['credit_amount'] ?? 0),
                'notes' => (string) ($sheet['notes'] ?? ''),
                'refund_notes' => (string) ($sheet['refund_notes'] ?? ''),
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
            $model = SalesReturn::query()->with('lines')->lockForUpdate()->find($sheet['id'] ?? null);

            if ($model === null) {
                throw ValidationException::withMessages(['return' => 'Return not found.']);
            }

            if ($model->status === SalesReturn::STATUS_COMPLETED) {
                return $this->toArray($model);
            }

            if (! $model->isEditable()) {
                throw ValidationException::withMessages(['return' => 'This return can no longer be completed.']);
            }

            if (blank($model->pos_sale_id)) {
                throw ValidationException::withMessages(['sale_number' => 'Load a completed sale before processing the return.']);
            }

            $this->validateReturnQuantities($sheet);

            $returnTotal = PosSaleReturnLineBuilder::subtotal($sheet['rows'] ?? []);
            $refundMethod = (string) ($sheet['refund_method'] ?? $model->refund_method);
            $refundAmount = PosSaleLineBuilder::numeric($sheet['refund_amount'] ?? 0);
            $creditAmount = PosSaleLineBuilder::numeric($sheet['credit_amount'] ?? 0);

            if ($refundMethod === 'customer_credit') {
                $creditAmount = $returnTotal;
                $refundAmount = 0;
                $refundStatus = SalesReturn::REFUND_CREDITED;
            } elseif ($refundMethod === 'original_payment' && $model->original_payment_method === 'credit') {
                $creditAmount = $returnTotal;
                $refundAmount = 0;
                $refundStatus = SalesReturn::REFUND_CREDITED;
            } else {
                if ($refundAmount <= 0) {
                    $refundAmount = $returnTotal;
                }
                $creditAmount = 0;
                $refundStatus = (string) ($sheet['refund_status'] ?? SalesReturn::REFUND_PAID);
                if ($refundStatus === SalesReturn::REFUND_PENDING && $refundAmount > 0) {
                    $refundStatus = SalesReturn::REFUND_PAID;
                }
            }

            $sheet['refund_amount'] = PosSaleLineBuilder::formatAmount($refundAmount);
            $sheet['credit_amount'] = PosSaleLineBuilder::formatAmount($creditAmount);
            $sheet['refund_status'] = $refundStatus;
            $sheet['status'] = SalesReturn::STATUS_COMPLETED;

            $this->update($sheet);

            $model->refresh()->load('lines');

            if (! $model->stock_applied) {
                $this->inventory->receivePosSaleReturn(
                    (string) $model->id,
                    (string) $model->store_key,
                    $this->rowsFromLines($model->lines),
                );

                $model->update([
                    'stock_applied' => true,
                    'completed_at' => now(),
                    'status' => SalesReturn::STATUS_COMPLETED,
                ]);
            }

            if ($creditAmount > 0 && $model->customer_id) {
                $this->customerAccounts->creditFromSaleReturn(
                    (int) $model->customer_id,
                    $creditAmount,
                    (string) $model->id,
                    'Return ' . $model->return_number . ' for sale ' . $model->sale_number,
                );
            }

            if (filled($model->pos_sale_id)) {
                app(PosSaleReturnSummary::class)->syncTotalsToSale((string) $model->pos_sale_id);
            }

            return $this->toArray($model->fresh('lines'));
        });
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    protected function validateReturnQuantities(array $sheet): void
    {
        $posSaleId = (string) ($sheet['pos_sale_id'] ?? '');

        if ($posSaleId === '') {
            throw ValidationException::withMessages(['rows' => 'Sale reference is missing.']);
        }

        $sale = $this->posSales->find($posSaleId);

        if ($sale === null) {
            throw ValidationException::withMessages(['sale_number' => 'Original sale not found.']);
        }

        $saleLines = collect($sale['rows'] ?? [])->keyBy('line_id');
        $returnedByLine = PosSaleReturnLineBuilder::returnedQtyBySourceLine($posSaleId, (string) ($sheet['id'] ?? null));

        $hasLine = false;

        foreach ($sheet['rows'] ?? [] as $row) {
            $returnQty = PosSaleLineBuilder::numeric($row['return_qty'] ?? $row['qty'] ?? '');

            if ($returnQty <= 0) {
                continue;
            }

            $hasLine = true;
            $sourceId = (string) ($row['source_sale_line_id'] ?? '');
            $saleLine = $saleLines->get($sourceId);

            if ($saleLine === null) {
                throw ValidationException::withMessages(['rows' => 'Return line does not match the original sale.']);
            }

            $soldQty = PosSaleLineBuilder::numeric($saleLine['qty'] ?? '');
            $alreadyReturned = $returnedByLine[$sourceId] ?? 0.0;
            $returnable = $soldQty - $alreadyReturned;

            if ($returnQty > $returnable + 0.0001) {
                throw ValidationException::withMessages([
                    'rows' => 'Return quantity exceeds returnable quantity for ' . ($saleLine['product_number'] ?? 'product') . '.',
                ]);
            }
        }

        if (! $hasLine) {
            throw ValidationException::withMessages(['rows' => 'Enter at least one return quantity.']);
        }
    }

    public function delete(string $returnId): void
    {
        $return = SalesReturn::query()->find($returnId);

        if ($return === null || $return->status === SalesReturn::STATUS_COMPLETED) {
            return;
        }

        $return->delete();
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(SalesReturn $return): array
    {
        return [
            'id' => (string) $return->id,
            'return_number' => (string) $return->return_number,
            'status' => (string) $return->status,
            'return_date' => $return->return_date?->toDateString() ?? '',
            'pos_sale_id' => $return->pos_sale_id,
            'sale_number' => (string) ($return->sale_number ?? ''),
            'name_lang' => (string) $return->name_lang,
            'customer_id' => $return->customer_id,
            'customer_name' => (string) ($return->customer_name ?? ''),
            'customer_mobile' => (string) ($return->customer_mobile ?? ''),
            'store_key' => (string) $return->store_key,
            'store_name' => (string) ($return->store_name ?? ''),
            'original_payment_method' => (string) ($return->original_payment_method ?? ''),
            'refund_method' => (string) $return->refund_method,
            'refund_status' => (string) $return->refund_status,
            'return_subtotal' => PosSaleLineBuilder::formatAmount((float) $return->return_subtotal),
            'return_total' => PosSaleLineBuilder::formatAmount((float) $return->return_total),
            'refund_amount' => PosSaleLineBuilder::formatAmount((float) $return->refund_amount),
            'credit_amount' => PosSaleLineBuilder::formatAmount((float) $return->credit_amount),
            'notes' => (string) ($return->notes ?? ''),
            'refund_notes' => (string) ($return->refund_notes ?? ''),
            'stock_applied' => (bool) $return->stock_applied,
            'rows' => $this->rowsFromLines($return->lines),
            'completed_at' => $return->completed_at?->toIso8601String(),
            'created_at' => $return->created_at?->toIso8601String() ?? '',
            'updated_at' => $return->updated_at?->toIso8601String() ?? '',
        ];
    }
}
