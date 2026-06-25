<?php

namespace App\Services\PurchasingInventory;

use App\Services\Settings\PrintingSettingResolver;

class PriceTagPresenter
{
    public function __construct(
        protected PriceTagBarcodeGenerator $barcodeGenerator,
        protected PrintingSettingResolver $printingResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function stickerData(array $line, array $settings): array
    {
        $fields = is_array($settings['fields'] ?? null) ? $settings['fields'] : [];
        $scanMode = (string) ($settings['scan_mode'] ?? 'barcode');
        $storeName = strtoupper((string) config('agricart.brand.name', 'Agricart ERP'));
        $barcodeValue = (string) ($line['barcode'] ?? $line['sku'] ?? '');
        $showBarcode = in_array($scanMode, ['barcode', 'both'], true) && $scanMode !== 'none';

        $saleDigits = $this->saleDigits((string) ($line['sale_rate'] ?? ''));

        $label = is_array($settings['label'] ?? null)
            ? $settings['label']
            : $this->printingResolver->priceTagLabel();

        return [
            'store_name' => ($fields['store_name'] ?? false) ? $storeName : '',
            'name_en' => ($fields['name_en'] ?? false) ? (string) ($line['name_en'] ?? '') : '',
            'name_ur' => ($fields['name_ur'] ?? false) ? (string) ($line['name_ur'] ?? '') : '',
            'brand' => ($fields['brand'] ?? false) ? (string) ($line['brand_name'] ?? '') : '',
            'unit' => ($fields['unit'] ?? false) ? (string) ($line['unit_label'] ?? '') : '',
            'sku' => ($fields['sku'] ?? false) ? (string) ($line['sku'] ?? '') : '',
            'sale_price' => ($fields['sale_price'] ?? false) ? $saleDigits : '',
            'purchase_price' => ($fields['purchase_price'] ?? false) ? (string) ($line['purchase_rate'] ?? '') : '',
            'landing_cost' => ($fields['landing_cost'] ?? false) ? (string) ($line['landing_cost'] ?? '') : '',
            'wholesale' => ($fields['wholesale'] ?? false) ? (string) ($line['wholesale_rate'] ?? '') : '',
            'super_wholesale' => ($fields['super_wholesale'] ?? false) ? (string) ($line['super_wholesale_rate'] ?? '') : '',
            'distributor' => ($fields['distributor'] ?? false) ? (string) ($line['distributor_rate'] ?? '') : '',
            'tier_codes' => ($fields['tier_codes'] ?? false) ? (string) ($line['tier_codes'] ?? '') : '',
            'purchase_code' => ($fields['purchase_code'] ?? false) ? (string) ($line['purchase_code'] ?? '') : '',
            'barcode_value' => $barcodeValue,
            'barcode_svg' => $showBarcode ? $this->barcodeGenerator->svg($barcodeValue) : null,
            'qr_url' => (string) ($line['qr_url'] ?? ''),
            'show_barcode' => $showBarcode,
            'show_qr' => in_array($scanMode, ['qr', 'both'], true),
            'compact_name' => $this->compactName($line, $fields),
            'label_width_mm' => (float) ($label['width_mm'] ?? 38),
            'label_height_mm' => (float) ($label['height_mm'] ?? 25),
            'label_gap_mm' => (float) ($label['gap_mm'] ?? 3),
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, bool>  $fields
     */
    protected function compactName(array $line, array $fields): string
    {
        $parts = [];

        if ($fields['name_en'] ?? false) {
            $parts[] = trim((string) ($line['name_en'] ?? ''));
        }

        if ($fields['name_ur'] ?? false) {
            $parts[] = trim((string) ($line['name_ur'] ?? ''));
        }

        return trim(implode(' / ', array_filter($parts)));
    }

    protected function saleDigits(string $saleRate): string
    {
        $value = str_replace(',', '', trim($saleRate));

        if ($value === '' || ! is_numeric($value)) {
            return '';
        }

        $number = (float) $value;

        if ($number <= 0) {
            return '';
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
