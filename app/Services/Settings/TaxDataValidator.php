<?php

namespace App\Services\Settings;

use App\Models\Tax;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaxDataValidator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, ?Tax $tax = null): void
    {
        $typeOptions = array_keys(config('tax.types', []));
        $statusOptions = array_keys(config('tax.statuses', []));
        $applyOnOptions = array_keys(config('tax.apply_on', []));

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('taxes', 'code')->ignore($tax?->id),
            ],
            'type' => ['required', Rule::in($typeOptions)],
            'rate_value' => ['required', 'numeric', 'min:0', 'max:9999999999.9999'],
            'apply_on' => ['required', 'array', 'min:1'],
            'apply_on.*' => ['required', Rule::in($applyOnOptions)],
            'status' => ['required', Rule::in($statusOptions)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'name.required' => 'Tax name is required.',
            'type.in' => 'Select a valid tax type.',
            'rate_value.required' => 'Tax rate / value is required.',
            'apply_on.required' => 'Select at least one apply-on option.',
            'apply_on.min' => 'Select at least one apply-on option.',
            'code.unique' => 'This tax code is already in use.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        if ($data['type'] === Tax::TYPE_PERCENTAGE && (float) $data['rate_value'] > 100) {
            throw ValidationException::withMessages([
                'rate_value' => 'Percentage tax cannot be greater than 100.',
            ]);
        }
    }
}
