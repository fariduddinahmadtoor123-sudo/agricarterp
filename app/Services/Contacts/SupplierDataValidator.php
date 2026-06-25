<?php

namespace App\Services\Contacts;

use App\Models\Supplier;
use App\Services\Contacts\Concerns\ValidatesContactMobileNumbers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplierDataValidator
{
    use ValidatesContactMobileNumbers;

    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'country' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'full_address' => ['required', 'string'],
            'business_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:150'],
            'mobile_number' => ['required', 'string', 'max:20'],
            'supplier_type' => ['nullable', 'string', 'max:30'],
            'status' => [
                'nullable',
                Rule::in([Supplier::STATUS_ACTIVE, Supplier::STATUS_INACTIVE]),
            ],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance_type' => [
                'nullable',
                Rule::in([Supplier::OPENING_BALANCE_DEBIT, Supplier::OPENING_BALANCE_CREDIT]),
            ],
            'bank_accounts' => ['required', 'array', 'min:1'],
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:150'],
            'bank_accounts.*.branch_name' => ['nullable', 'string', 'max:150'],
            'bank_accounts.*.account_title' => ['nullable', 'string', 'max:150'],
            'bank_accounts.*.iban_account_number' => ['nullable', 'string', 'max:50'],
            'additional_contacts' => ['nullable', 'array'],
            'additional_contacts.*.contact_person' => ['nullable', 'string', 'max:150'],
            'additional_contacts.*.mobile_number' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $this->assertPrimaryMobileIsNormalizable($data);
    }
}
