<?php

namespace App\Services\SalesPos;

use App\Models\SalesPos\PosSale;
use App\Models\SalesPos\SalesReturn;

class PosSaleReturnSummary
{
    /**
     * @return array<string, mixed>
     */
    public function forSale(string $posSaleId): array
    {
        $sale = PosSale::query()->find($posSaleId);

        if ($sale === null) {
            return $this->emptySummary();
        }

        $returns = SalesReturn::query()
            ->where('pos_sale_id', $posSaleId)
            ->where('status', SalesReturn::STATUS_COMPLETED)
            ->orderByDesc('completed_at')
            ->get();

        $returnTotal = round((float) $returns->sum('return_total'), 2);
        $refundTotal = round((float) $returns->sum('refund_amount'), 2);
        $creditTotal = round((float) $returns->sum('credit_amount'), 2);
        $grossTotal = round((float) $sale->total, 2);
        $netTotal = max(0, round($grossTotal - $returnTotal, 2));
        $amountPaid = round((float) $sale->amount_paid, 2);

        $netAmountPaid = match ((string) $sale->payment_method) {
            'credit' => $amountPaid,
            default => max(0, round($amountPaid - $refundTotal, 2)),
        };

        $netReceivable = match ((string) $sale->payment_method) {
            'credit' => $netTotal,
            default => max(0, round($netTotal - $netAmountPaid, 2)),
        };

        return [
            'has_returns' => $returns->isNotEmpty(),
            'return_count' => $returns->count(),
            'gross_total' => PosSaleLineBuilder::formatAmount($grossTotal),
            'return_total' => PosSaleLineBuilder::formatAmount($returnTotal),
            'refund_total' => PosSaleLineBuilder::formatAmount($refundTotal),
            'credit_total' => PosSaleLineBuilder::formatAmount($creditTotal),
            'net_total' => PosSaleLineBuilder::formatAmount($netTotal),
            'amount_paid' => PosSaleLineBuilder::formatAmount($amountPaid),
            'net_amount_paid' => PosSaleLineBuilder::formatAmount($netAmountPaid),
            'net_receivable' => PosSaleLineBuilder::formatAmount($netReceivable),
            'payment_method' => (string) $sale->payment_method,
            'returned_qty_by_line' => PosSaleReturnLineBuilder::returnedQtyBySourceLine($posSaleId),
            'returns' => $returns->map(fn (SalesReturn $return): array => [
                'id' => (string) $return->id,
                'return_number' => (string) $return->return_number,
                'return_date' => $return->return_date?->toDateString() ?? '',
                'return_total' => PosSaleLineBuilder::formatAmount((float) $return->return_total),
                'refund_method' => (string) $return->refund_method,
                'refund_status' => (string) $return->refund_status,
                'refund_amount' => PosSaleLineBuilder::formatAmount((float) $return->refund_amount),
                'credit_amount' => PosSaleLineBuilder::formatAmount((float) $return->credit_amount),
            ])->all(),
        ];
    }

    public function syncTotalsToSale(string $posSaleId): void
    {
        $sale = PosSale::query()->find($posSaleId);

        if ($sale === null || $sale->status !== PosSale::STATUS_COMPLETED) {
            return;
        }

        $returns = SalesReturn::query()
            ->where('pos_sale_id', $posSaleId)
            ->where('status', SalesReturn::STATUS_COMPLETED)
            ->get();

        $returnTotal = round((float) $returns->sum('return_total'), 2);
        $refundTotal = round((float) $returns->sum('refund_amount'), 2);
        $creditTotal = round((float) $returns->sum('credit_amount'), 2);
        $grossTotal = round((float) $sale->total, 2);
        $netTotal = max(0, round($grossTotal - $returnTotal, 2));

        $sale->update([
            'return_total' => $returnTotal,
            'refund_total' => $refundTotal,
            'credit_return_total' => $creditTotal,
            'net_total' => $netTotal,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptySummary(): array
    {
        return [
            'has_returns' => false,
            'return_count' => 0,
            'gross_total' => '0',
            'return_total' => '0',
            'refund_total' => '0',
            'credit_total' => '0',
            'net_total' => '0',
            'amount_paid' => '0',
            'net_amount_paid' => '0',
            'net_receivable' => '0',
            'payment_method' => '',
            'returned_qty_by_line' => [],
            'returns' => [],
        ];
    }
}
