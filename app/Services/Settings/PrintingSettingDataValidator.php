<?php

namespace App\Services\Settings;

use App\Models\PrintingSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PrintingSettingDataValidator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, ?PrintingSetting $setting = null): void
    {
        $documentPapers = array_keys(config('printing.document_paper_sizes', []));
        $labelPresets = array_keys(config('printing.label_presets', []));
        $sheetPapers = array_keys(config('printing.label_sheet_papers', []));
        $thermalProfiles = array_keys(config('printing.thermal_receipt_profiles', []));

        $validator = Validator::make($data, [
            'default_document_paper' => ['required', Rule::in($documentPapers)],
            'default_purchase_invoice_paper' => ['required', Rule::in($documentPapers)],
            'price_tag_label_preset' => ['required', Rule::in($labelPresets)],
            'price_tag_width_mm' => ['required', 'numeric', 'min:10', 'max:200'],
            'price_tag_height_mm' => ['required', 'numeric', 'min:10', 'max:200'],
            'price_tag_gap_mm' => ['required', 'numeric', 'min:0', 'max:20'],
            'price_tag_sheet_paper' => ['required', Rule::in($sheetPapers)],
            'barcode_printer_note' => ['nullable', 'string', 'max:500'],
            'pos_receipt_profile' => ['required', Rule::in($thermalProfiles)],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }
}
