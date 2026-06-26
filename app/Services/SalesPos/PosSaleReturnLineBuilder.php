<?php

namespace App\Services\SalesPos;

use App\Models\SalesPos\SalesReturn;
use App\Models\SalesPos\SalesReturnLine;
use Illuminate\Support\Str;

class PosSaleReturnLineBuilder
{
    /**
     * @param  array<string, mixed>  $saleLine
     * @return array<string, mixed>
     */
    public static function fromSaleLine(array $saleLine, float $previouslyReturned = 0): array
    {
        $soldQty = PosSaleLineBuilder::numeric($saleLine['qty'] ?? '');
        $sourceLineId = (string) ($saleLine['line_id'] ?? '');
        $returnable = max(0, $soldQty - $previouslyReturned);
        $unitPrice = PosSaleLineBuilder::numeric($saleLine['unit_price'] ?? '');

        return [
            'line_id' => (string) Str::uuid(),
            'source_sale_line_id' => $sourceLineId,
            'product_id' => (int) ($saleLine['product_id'] ?? 0),
            'thumbnail_url' => $saleLine['thumbnail_url'] ?? null,
            'product_number' => (string) ($saleLine['product_number'] ?? ''),
            'name_en' => (string) ($saleLine['name_en'] ?? ''),
            'name_ur' => (string) ($saleLine['name_ur'] ?? ''),
            'brand_name' => (string) ($saleLine['brand_name'] ?? ''),
            'attributes_label' => (string) ($saleLine['attributes_label'] ?? ''),
            'unit_label' => (string) ($saleLine['unit_label'] ?? ''),
            'sold_qty' => PosSaleLineBuilder::formatQuantity($soldQty),
            'previously_returned_qty' => PosSaleLineBuilder::formatQuantity($previouslyReturned),
            'returnable_qty' => PosSaleLineBuilder::formatQuantity($returnable),
            'return_qty' => '',
            'qty' => '',
            'unit_price' => PosSaleLineBuilder::formatAmount($unitPrice),
            'line_total' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function recalculate(array $row): array
    {
        $returnQty = PosSaleLineBuilder::numeric($row['return_qty'] ?? $row['qty'] ?? '');
        $unitPrice = PosSaleLineBuilder::numeric($row['unit_price'] ?? '');
        $row['return_qty'] = PosSaleLineBuilder::formatQuantity($returnQty);
        $row['qty'] = $row['return_qty'];
        $row['line_total'] = PosSaleLineBuilder::formatAmount($returnQty * $unitPrice);

        return $row;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function subtotal(array $rows): float
    {
        return round(collect($rows)->sum(function (array $row): float {
            $qty = PosSaleLineBuilder::numeric($row['return_qty'] ?? $row['qty'] ?? '');

            if ($qty <= 0) {
                return 0.0;
            }

            return PosSaleLineBuilder::numeric($row['line_total'] ?? '');
        }), 2);
    }

    /**
     * @return array<string, float>
     */
    public static function returnedQtyBySourceLine(string $posSaleId, ?string $excludeReturnId = null): array
    {
        $query = SalesReturn::query()
            ->where('pos_sale_id', $posSaleId)
            ->where('status', SalesReturn::STATUS_COMPLETED);

        if (filled($excludeReturnId)) {
            $query->where('id', '!=', $excludeReturnId);
        }

        $returnIds = $query->pluck('id');

        if ($returnIds->isEmpty()) {
            return [];
        }

        $totals = [];

        SalesReturnLine::query()
            ->whereIn('return_id', $returnIds)
            ->get()
            ->each(function (SalesReturnLine $line) use (&$totals): void {
                $payload = $line->payload ?? [];
                $sourceId = (string) ($payload['source_sale_line_id'] ?? $line->source_sale_line_id ?? '');

                if ($sourceId === '') {
                    return;
                }

                $qty = PosSaleLineBuilder::numeric($payload['return_qty'] ?? $payload['qty'] ?? '');
                $totals[$sourceId] = ($totals[$sourceId] ?? 0) + $qty;
            });

        return $totals;
    }
}
