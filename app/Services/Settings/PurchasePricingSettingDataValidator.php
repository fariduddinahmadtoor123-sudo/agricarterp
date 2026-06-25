<?php

namespace App\Services\Settings;

use App\Models\PurchasePricingSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PurchasePricingSettingDataValidator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, ?PurchasePricingSetting $record = null): void
    {
        $validator = Validator::make($data, [
            'update_product_prices_from_purchases' => ['required', 'boolean'],
            'wholesale_markup_pct' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'super_wholesale_markup_pct' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'distributor_markup_pct' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'price_code_wording' => ['required', 'array'],
            'price_code_wording.0' => ['required', 'string', 'max:30'],
            'price_code_wording.1' => ['required', 'string', 'max:30'],
            'price_code_wording.2' => ['required', 'string', 'max:30'],
            'price_code_wording.3' => ['required', 'string', 'max:30'],
            'price_code_wording.4' => ['required', 'string', 'max:30'],
            'price_code_wording.5' => ['required', 'string', 'max:30'],
            'price_code_wording.6' => ['required', 'string', 'max:30'],
            'price_code_wording.7' => ['required', 'string', 'max:30'],
            'price_code_wording.8' => ['required', 'string', 'max:30'],
            'price_code_wording.9' => ['required', 'string', 'max:30'],
        ], [
            'wholesale_markup_pct.required' => 'Wholesale markup % is required.',
            'super_wholesale_markup_pct.required' => 'Super wholesale markup % is required.',
            'distributor_markup_pct.required' => 'Distributor markup % is required.',
            'price_code_wording.required' => 'Price code wording is required.',
            'price_code_wording.*.required' => 'Each digit 0–9 must have wording.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        if ($record === null && PurchasePricingSetting::query()->exists()) {
            throw ValidationException::withMessages([
                'wholesale_markup_pct' => 'Purchase pricing settings already exist. Edit the existing record instead.',
            ]);
        }
    }
}
