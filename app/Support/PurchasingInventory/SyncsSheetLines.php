<?php

namespace App\Support\PurchasingInventory;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

trait SyncsSheetLines
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function syncLineRows(Model $sheet, HasMany $linesRelation, array $rows): void
    {
        $existing = $linesRelation->get()->keyBy('line_id');
        $seenLineIds = [];

        foreach (array_values($rows) as $index => $row) {
            $lineId = (string) ($row['line_id'] ?? '');

            if ($lineId === '') {
                $lineId = (string) Str::uuid();
                $row['line_id'] = $lineId;
            }

            $seenLineIds[] = $lineId;
            $productId = isset($row['product_id']) && is_numeric($row['product_id'])
                ? (int) $row['product_id']
                : null;

            if ($productId > 0 && ! Product::query()->whereKey($productId)->exists()) {
                $productId = null;
            }

            $payload = [
                'line_id' => $lineId,
                'product_id' => $productId,
                'sort_order' => $index,
                'payload' => $row,
            ];

            if ($existing->has($lineId)) {
                $existing->get($lineId)->update($payload);
            } else {
                $linesRelation->create($payload);
            }
        }

        if ($seenLineIds === []) {
            $linesRelation->delete();

            return;
        }

        $linesRelation->whereNotIn('line_id', $seenLineIds)->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function rowsFromLines(iterable $lines): array
    {
        $rows = [];

        foreach ($lines as $line) {
            $payload = $line->payload ?? [];

            if (! is_array($payload)) {
                $payload = [];
            }

            if (! isset($payload['line_id'])) {
                $payload['line_id'] = (string) $line->line_id;
            }

            $rows[] = $payload;
        }

        return $rows;
    }
}
