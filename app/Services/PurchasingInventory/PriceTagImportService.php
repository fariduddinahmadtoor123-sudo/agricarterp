<?php

namespace App\Services\PurchasingInventory;

use App\Support\PurchasingInventory\PurchaseSheetRepository;

class PriceTagImportService
{
    public function __construct(
        protected PurchaseSheetRepository $purchaseRepository,
        protected PriceTagLineBuilder $lineBuilder,
    ) {}

    /**
     * @return array{lines: list<array<string, mixed>>, purchase_number: string}|null
     */
    public function linesFromPurchaseNumber(string $purchaseNumber): ?array
    {
        $sheet = $this->purchaseRepository->findByPurchaseNumber(trim($purchaseNumber));

        if ($sheet === null) {
            return null;
        }

        $purchaseNumber = (string) ($sheet['purchase_number'] ?? '');
        $rows = array_values($sheet['rows'] ?? []);
        $lines = [];

        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $purchaseQty = PurchaseLineBuilder::formatQuantity($row['purchase_qty'] ?? '');

            if ($productId === 0 || $purchaseQty === '') {
                continue;
            }

            $lines[] = $this->lineBuilder->fromPurchaseRow($row, $purchaseNumber);
        }

        return [
            'lines' => $lines,
            'purchase_number' => $purchaseNumber,
        ];
    }

    /**
     * @return list<array{id: string, purchase_number: string, label: string}>
     */
    public function purchaseInvoiceOptions(): array
    {
        return collect($this->purchaseRepository->all())
            ->filter(fn (array $sheet): bool => ($sheet['status'] ?? '') === 'saved')
            ->sortByDesc('updated_at')
            ->map(fn (array $sheet): array => [
                'id' => (string) $sheet['id'],
                'purchase_number' => (string) ($sheet['purchase_number'] ?? ''),
                'label' => trim(
                    (string) ($sheet['purchase_number'] ?? '')
                    . ' — '
                    . (string) ($sheet['supplier_name'] ?? 'Supplier')
                    . ' ('
                    . count($sheet['rows'] ?? [])
                    . ' lines)',
                ),
            ])
            ->values()
            ->all();
    }
}
