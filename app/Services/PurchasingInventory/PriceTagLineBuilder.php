<?php

namespace App\Services\PurchasingInventory;

use App\Models\Product;
use App\Models\PurchasingInventory\ProductStorePrice;
use App\Services\ProductCatalog\ProductControlAssignmentService;
use App\Services\ProductCatalog\ProductLabelQrGenerator;
use Illuminate\Support\Str;

class PriceTagLineBuilder
{
    public function __construct(
        protected ProductControlAssignmentService $controlAssignment,
        protected ProductLabelQrGenerator $qrGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public function fromCatalogProduct(array $product, int $printQty = 1): array
    {
        $model = Product::query()
            ->with(['brand', 'baseUnit', 'packingUnit', 'controlGroups', 'individualControls'])
            ->find((int) ($product['id'] ?? 0));

        $tierCodes = '';
        $purchaseCode = '';

        if ($model !== null) {
            $controls = $this->controlAssignment->effectiveControlLabels(
                $model->controlGroups->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                $model->individualControls->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            );
            $tierCodes = $this->formatTierCodes($controls);
            $purchaseCode = $this->purchaseCodeFromControls($controls);
        }

        $sku = (string) ($product['sku'] ?? $product['barcode'] ?? '');
        $unit = $model?->baseUnit?->abbreviation_en
            ?? $model?->baseUnit?->name_en
            ?? '';

        $storedPrices = $model !== null
            ? $this->storedPricesForProduct((int) $model->id)
            : [];

        return $this->baseLine([
            'product_id' => (int) ($product['id'] ?? 0),
            'sku' => $sku,
            'barcode' => (string) ($product['barcode'] ?? $sku),
            'name_en' => (string) ($product['name_en'] ?? ''),
            'name_ur' => (string) ($product['name_ur'] ?? ''),
            'brand_name' => (string) ($model?->brand?->name_en ?? ''),
            'unit_label' => trim($unit),
            'thumbnail_url' => $product['thumbnail_url'] ?? null,
            'purchase_qty' => '',
            'source_invoice' => '',
            'sale_rate' => $storedPrices['sale_rate'] ?? '',
            'purchase_rate' => $storedPrices['purchase_rate'] ?? '',
            'landing_cost' => $storedPrices['landing_cost'] ?? '',
            'wholesale_rate' => $storedPrices['wholesale_rate'] ?? '',
            'super_wholesale_rate' => $storedPrices['super_wholesale_rate'] ?? '',
            'distributor_rate' => $storedPrices['distributor_rate'] ?? '',
            'tier_codes' => $tierCodes,
            'purchase_code' => $purchaseCode !== '' ? $purchaseCode : $this->fallbackPurchaseCode($sku),
            'print_qty' => max(1, $printQty),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function fromPurchaseRow(array $row, string $purchaseNumber): array
    {
        $productId = (int) ($row['product_id'] ?? 0);
        $printQty = (int) PurchaseLineBuilder::formatQuantity($row['purchase_qty'] ?? '1');
        $printQty = $printQty > 0 ? $printQty : 1;

        $line = $this->fromCatalogProduct([
            'id' => $productId,
            'barcode' => $row['barcode'] ?? '',
            'sku' => $row['sku'] ?? '',
            'name_en' => $row['name_en'] ?? '',
            'name_ur' => $row['name_ur'] ?? '',
            'thumbnail_url' => $row['thumbnail_url'] ?? null,
        ], $printQty);

        $line['purchase_qty'] = PurchaseLineBuilder::formatQuantity($row['purchase_qty'] ?? '');
        $line['source_invoice'] = $purchaseNumber;
        $line['purchase_rate'] = (string) ($row['purchase_rate'] ?? '');
        $line['landing_cost'] = (string) ($row['landing_cost'] ?? '');
        $line['sale_rate'] = (string) ($row['sale_rate'] ?? '');
        $line['wholesale_rate'] = (string) ($row['wholesale_rate'] ?? '');
        $line['super_wholesale_rate'] = (string) ($row['super_wholesale_rate'] ?? '');
        $line['distributor_rate'] = (string) ($row['distributor_rate'] ?? '');

        return $line;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function baseLine(array $attributes): array
    {
        $sku = (string) ($attributes['sku'] ?? '');

        return [
            'line_id' => (string) Str::uuid(),
            'product_id' => (int) ($attributes['product_id'] ?? 0),
            'disabled' => false,
            'sku' => $sku,
            'barcode' => (string) ($attributes['barcode'] ?? $sku),
            'name_en' => (string) ($attributes['name_en'] ?? ''),
            'name_ur' => (string) ($attributes['name_ur'] ?? ''),
            'brand_name' => (string) ($attributes['brand_name'] ?? ''),
            'unit_label' => (string) ($attributes['unit_label'] ?? ''),
            'thumbnail_url' => $attributes['thumbnail_url'] ?? null,
            'purchase_qty' => (string) ($attributes['purchase_qty'] ?? ''),
            'source_invoice' => (string) ($attributes['source_invoice'] ?? ''),
            'sale_rate' => (string) ($attributes['sale_rate'] ?? ''),
            'purchase_rate' => (string) ($attributes['purchase_rate'] ?? ''),
            'landing_cost' => (string) ($attributes['landing_cost'] ?? ''),
            'wholesale_rate' => (string) ($attributes['wholesale_rate'] ?? ''),
            'super_wholesale_rate' => (string) ($attributes['super_wholesale_rate'] ?? ''),
            'distributor_rate' => (string) ($attributes['distributor_rate'] ?? ''),
            'tier_codes' => (string) ($attributes['tier_codes'] ?? ''),
            'purchase_code' => (string) ($attributes['purchase_code'] ?? ''),
            'print_qty' => max(1, (int) ($attributes['print_qty'] ?? 1)),
            'qr_url' => $this->qrGenerator->url($sku !== '' ? $sku : null),
        ];
    }

    /**
     * @param  list<string>  $controls
     */
    protected function formatTierCodes(array $controls): string
    {
        if ($controls === []) {
            return '';
        }

        return Str::limit(implode(' · ', $controls), 40, '');
    }

    /**
     * @param  list<string>  $controls
     */
    protected function purchaseCodeFromControls(array $controls): string
    {
        if ($controls === []) {
            return '';
        }

        $letters = collect($controls)
            ->map(fn (string $label): string => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $label) ?: 'X', 0, 1)))
            ->take(3)
            ->implode('');

        return $letters !== '' ? $letters : '';
    }

    protected function fallbackPurchaseCode(string $sku): string
    {
        $digits = preg_replace('/\D/', '', $sku) ?: '0';

        return strtoupper(substr($digits, -3));
    }

    /**
     * @return array<string, string>
     */
    protected function storedPricesForProduct(int $productId): array
    {
        $storeKey = (string) config('purchasing-inventory.demo_default_store', 'main');

        $price = ProductStorePrice::query()
            ->where('product_id', $productId)
            ->where('store_key', $storeKey)
            ->first();

        if ($price === null) {
            return [];
        }

        return [
            'purchase_rate' => $this->formatStoredAmount($price->purchase_rate),
            'landing_cost' => $this->formatStoredAmount($price->landing_cost),
            'sale_rate' => $this->formatStoredAmount($price->sale_rate),
            'wholesale_rate' => $this->formatStoredAmount($price->wholesale_rate),
            'super_wholesale_rate' => $this->formatStoredAmount($price->super_wholesale_rate),
            'distributor_rate' => $this->formatStoredAmount($price->distributor_rate),
        ];
    }

    protected function formatStoredAmount(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return PurchaseLineBuilder::formatAmount((float) $value);
    }
}
