<?php

namespace App\Services\PurchasingInventory;

use App\Models\Supplier;
use App\Support\PurchasingInventory\PurchasePlanningSheetRepository;
use App\Support\PurchasingInventory\PurchaseQuotationSheetRepository;

class PurchaseSheetImportService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function planningOptions(): array
    {
        return collect(app(PurchasePlanningSheetRepository::class)->all())
            ->map(fn (array $sheet): array => [
                'id' => (string) ($sheet['id'] ?? ''),
                'number' => (string) ($sheet['sheet_number'] ?? ''),
                'label' => (string) ($sheet['sheet_number'] ?? '') . ' — ' . (string) ($sheet['title'] ?? 'Planning Sheet'),
            ])
            ->filter(fn (array $option): bool => $option['id'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function quotationOptions(): array
    {
        return collect(app(PurchaseQuotationSheetRepository::class)->all())
            ->map(fn (array $sheet): array => [
                'id' => (string) ($sheet['id'] ?? ''),
                'number' => (string) ($sheet['quotation_number'] ?? ''),
                'label' => (string) ($sheet['quotation_number'] ?? '') . ' — ' . (string) ($sheet['supplier_name'] ?? 'Quotation'),
            ])
            ->filter(fn (array $option): bool => $option['id'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{rows: list<array<string, mixed>>, meta: array<string, mixed>}|null
     */
    public function fromPlanning(string $planningId): ?array
    {
        $sheet = app(PurchasePlanningSheetRepository::class)->find($planningId);

        if ($sheet === null) {
            return null;
        }

        $builder = app(PurchaseLineBuilder::class);
        $rows = [];

        foreach ($sheet['rows'] ?? [] as $row) {
            $product = [
                'id' => $row['product_id'] ?? 0,
                'thumbnail_url' => $row['thumbnail_url'] ?? null,
                'barcode' => $row['barcode'] ?? '',
                'sku' => $row['sku'] ?? '',
                'name_en' => $row['name_en'] ?? '',
                'name_ur' => $row['name_ur'] ?? '',
                'low_stock' => $row['low_stock'] ?? '',
            ];

            $purchaseRow = $builder->fromProduct($product);
            $purchaseRow['required_qty'] = PurchaseLineBuilder::formatQuantity($row['required_qty'] ?? '');
            $purchaseRow['alert_qty'] = PurchaseLineBuilder::formatQuantity($row['low_stock'] ?? $purchaseRow['alert_qty']);
            $purchaseRow['previous_rate'] = (string) ($row['purchase_price'] ?? '');
            $purchaseRow['purchase_rate'] = (string) ($row['purchase_price'] ?? '');
            $purchaseRow['landing_cost'] = (string) ($row['landing_cost'] ?? '');
            $purchaseRow['sale_rate'] = (string) ($row['sale_price'] ?? '');
            $purchaseRow = PurchaseLineBuilder::applyTierRates($purchaseRow, syncMarkupsFromSettings: true);

            $rows[] = $purchaseRow;
        }

        return [
            'rows' => $rows,
            'meta' => [
                'linked_planning_id' => (string) ($sheet['id'] ?? ''),
                'linked_planning_number' => (string) ($sheet['sheet_number'] ?? ''),
                'name_lang' => (string) ($sheet['name_lang'] ?? 'both'),
                'notes' => (string) ($sheet['notes'] ?? ''),
            ],
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, meta: array<string, mixed>}|null
     */
    public function fromQuotation(string $quotationId): ?array
    {
        $sheet = app(PurchaseQuotationSheetRepository::class)->find($quotationId);

        if ($sheet === null) {
            return null;
        }

        $builder = app(PurchaseLineBuilder::class);
        $rows = [];

        foreach ($sheet['rows'] ?? [] as $row) {
            $product = [
                'id' => $row['product_id'] ?? 0,
                'thumbnail_url' => $row['thumbnail_url'] ?? null,
                'barcode' => $row['barcode'] ?? '',
                'sku' => $row['sku'] ?? '',
                'name_en' => $row['name_en'] ?? '',
                'name_ur' => $row['name_ur'] ?? '',
                'low_stock' => '',
            ];

            $purchaseRow = $builder->fromProduct($product);
            $purchaseRow['required_qty'] = PurchaseLineBuilder::formatQuantity($row['required_qty'] ?? '');
            $purchaseRow['purchase_qty'] = PurchaseLineBuilder::formatQuantity($row['required_qty'] ?? '');
            $purchaseRow['previous_rate'] = (string) ($row['unit_price'] ?? '');
            $purchaseRow['purchase_rate'] = (string) ($row['unit_price'] ?? '');
            $purchaseRow = PurchaseLineBuilder::applyTierRates($purchaseRow, syncMarkupsFromSettings: true);

            $rows[] = $purchaseRow;
        }

        return [
            'rows' => $rows,
            'meta' => [
                'linked_quotation_id' => (string) ($sheet['id'] ?? ''),
                'linked_quotation_number' => (string) ($sheet['quotation_number'] ?? ''),
                'supplier_id' => $sheet['supplier_id'] ?? null,
                'supplier_name' => (string) ($sheet['supplier_name'] ?? ''),
                'store_key' => (string) ($sheet['store_key'] ?? ''),
                'store_name' => (string) ($sheet['store_name'] ?? ''),
                'name_lang' => (string) ($sheet['name_lang'] ?? 'both'),
                'notes' => (string) ($sheet['notes'] ?? ''),
            ],
        ];
    }

    /**
     * @return array{amount: float, formatted: string, label: string}
     */
    public function supplierBalance(?int $supplierId): array
    {
        if ($supplierId === null) {
            return [
                'amount' => 0.0,
                'formatted' => '0.00',
                'label' => 'No supplier selected',
            ];
        }

        $supplier = Supplier::operational()->find($supplierId);

        if ($supplier === null) {
            return [
                'amount' => 0.0,
                'formatted' => '0.00',
                'label' => 'Supplier not found',
            ];
        }

        $amount = (float) $supplier->opening_balance;
        $label = $supplier->opening_balance_type === Supplier::OPENING_BALANCE_CREDIT
            ? 'Previous balance (Payable)'
            : 'Previous balance (Receivable)';

        return [
            'amount' => $amount,
            'formatted' => number_format($amount, 2, '.', ','),
            'label' => $label,
        ];
    }
}
