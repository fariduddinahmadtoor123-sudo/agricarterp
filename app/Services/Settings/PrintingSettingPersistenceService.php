<?php

namespace App\Services\Settings;

use App\Models\PrintingSetting;
use Illuminate\Support\Facades\DB;

class PrintingSettingPersistenceService
{
    public function __construct(
        protected PrintingSettingDataValidator $dataValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PrintingSetting
    {
        $data = $this->prepareData($data);
        $this->dataValidator->validate($data);

        return DB::transaction(function () use ($data): PrintingSetting {
            return PrintingSetting::query()->create($this->attributes($data));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PrintingSetting $setting, array $data): PrintingSetting
    {
        $data = $this->prepareData($data);
        $this->dataValidator->validate($data, $setting);

        return DB::transaction(function () use ($setting, $data): PrintingSetting {
            $setting->update($this->attributes($data));

            return $setting->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data): array
    {
        $preset = (string) ($data['price_tag_label_preset'] ?? '38x25');

        if ($preset !== 'custom') {
            $presetConfig = config("printing.label_presets.{$preset}", []);

            $data['price_tag_width_mm'] = $presetConfig['width_mm'] ?? $data['price_tag_width_mm'] ?? 38;
            $data['price_tag_height_mm'] = $presetConfig['height_mm'] ?? $data['price_tag_height_mm'] ?? 25;
            $data['price_tag_gap_mm'] = $presetConfig['gap_mm'] ?? $data['price_tag_gap_mm'] ?? 3;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributes(array $data): array
    {
        return [
            'default_document_paper' => $data['default_document_paper'],
            'default_purchase_invoice_paper' => $data['default_purchase_invoice_paper'],
            'price_tag_label_preset' => $data['price_tag_label_preset'],
            'price_tag_width_mm' => $data['price_tag_width_mm'],
            'price_tag_height_mm' => $data['price_tag_height_mm'],
            'price_tag_gap_mm' => $data['price_tag_gap_mm'],
            'price_tag_sheet_paper' => $data['price_tag_sheet_paper'],
            'barcode_printer_note' => $data['barcode_printer_note'] ?? null,
            'pos_receipt_profile' => $data['pos_receipt_profile'],
        ];
    }
}
