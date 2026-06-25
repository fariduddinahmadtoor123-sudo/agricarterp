<?php

namespace App\Services\Settings;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CompanySettingDataValidator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, ?CompanySetting $record = null): void
    {
        $decimalOptions = array_keys(config('settings.decimal_places', []));
        $currencyOptions = array_keys(config('settings.currencies', []));
        $timezoneOptions = array_keys(config('settings.timezones', []));

        $validator = Validator::make($data, [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ur' => ['nullable', 'string', 'max:255'],
            'address_en' => ['nullable', 'string', 'max:5000'],
            'address_ur' => ['nullable', 'string', 'max:5000'],
            'phones' => ['nullable', 'array'],
            'phones.*.contact_person' => ['nullable', 'string', 'max:150'],
            'phones.*.phone_number' => ['nullable', 'string', 'max:30'],
            'whatsapp_numbers' => ['nullable', 'array'],
            'whatsapp_numbers.*.contact_person' => ['nullable', 'string', 'max:150'],
            'whatsapp_numbers.*.whatsapp_number' => ['nullable', 'string', 'max:30'],
            'emails' => ['nullable', 'array'],
            'emails.*' => ['email', 'max:255'],
            'website_url' => ['nullable', 'string', 'max:255', 'url'],
            'ntn' => ['nullable', 'string', 'max:30'],
            'strn' => ['nullable', 'string', 'max:30'],
            'currency' => ['required', 'string', 'in:' . implode(',', $currencyOptions)],
            'decimal_places' => ['required', 'integer', 'in:' . implode(',', $decimalOptions)],
            'timezone' => ['required', 'string', 'in:' . implode(',', $timezoneOptions)],
        ], [
            'name_en.required' => 'English name is required.',
            'currency.in' => 'Select a valid currency.',
            'timezone.in' => 'Select a valid timezone.',
            'emails.*.email' => 'Enter a valid email address.',
            'website_url.url' => 'Enter a valid website URL.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        if ($record === null && CompanySetting::query()->exists()) {
            throw ValidationException::withMessages([
                'name_en' => 'Company / main store settings already exist. Edit the existing record instead.',
            ]);
        }
    }
}
