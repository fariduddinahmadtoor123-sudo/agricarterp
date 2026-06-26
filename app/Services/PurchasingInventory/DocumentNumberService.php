<?php

namespace App\Services\PurchasingInventory;

use App\Models\PurchasingInventory\DocumentNumberSequence;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    /** @var array<string, string> */
    protected const PREFIXES = [
        'purchase_planning' => 'PP',
        'purchase_quotation' => 'PQ',
        'purchase' => 'PU',
        'purchase_payment' => 'PPS',
        'reorder' => 'RO',
        'stock_adjustment' => 'SA',
        'opening_stock' => 'OS',
        'pos_sale' => 'PS',
        'sales_quotation' => 'SQ',
        'sales_return' => 'SR',
    ];

    public function next(string $documentType, ?\DateTimeInterface $date = null): string
    {
        $prefix = self::PREFIXES[$documentType] ?? strtoupper(substr($documentType, 0, 3));
        $sequenceDate = ($date ?? now())->toDateString();
        $dateToken = ($date ?? now())->format('Ymd');

        $sequenceNumber = DB::transaction(function () use ($documentType, $sequenceDate): int {
            $sequence = DocumentNumberSequence::query()
                ->where('document_type', $documentType)
                ->whereDate('sequence_date', $sequenceDate)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = DocumentNumberSequence::query()->create([
                    'document_type' => $documentType,
                    'sequence_date' => $sequenceDate,
                    'last_number' => 0,
                ]);
            }

            $sequence->last_number = (int) $sequence->last_number + 1;
            $sequence->save();

            return (int) $sequence->last_number;
        });

        return sprintf('%s-%s-%04d', $prefix, $dateToken, $sequenceNumber);
    }
}
