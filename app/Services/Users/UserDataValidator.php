<?php

namespace App\Services\Users;

use App\Models\Role;
use App\Models\User;
use App\Models\UserApplication;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserDataValidator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, ?User $user = null, bool $requirePassword = false): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'full_address' => ['nullable', 'string'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('slug', '!=', Role::SLUG_SUPER_ADMIN)),
            ],
            'status' => [
                'nullable',
                Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE]),
            ],
            'phones' => ['required', 'array', 'min:1'],
            'phones.*.contact_person' => ['nullable', 'string', 'max:150'],
            'phones.*.phone_number' => ['required', 'string', 'max:30'],
            'bank_accounts' => ['required', 'array', 'min:1'],
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:150'],
            'bank_accounts.*.branch_name' => ['nullable', 'string', 'max:150'],
            'bank_accounts.*.account_title' => ['nullable', 'string', 'max:150'],
            'bank_accounts.*.iban_account_number' => ['nullable', 'string', 'max:64'],
            'documents' => ['nullable', 'array'],
            'documents.profile_photo' => ['nullable'],
            'documents.card_front' => ['nullable'],
            'documents.card_back' => ['nullable'],
        ];

        if ($requirePassword || filled($data['password'] ?? null)) {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function validateApplication(array $data): void
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'full_address' => ['nullable', 'string'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
                Rule::unique('user_applications', 'email')->where(fn ($query) => $query->where('status', UserApplication::STATUS_PENDING)),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phones' => ['required', 'array', 'min:1'],
            'phones.*.contact_person' => ['nullable', 'string', 'max:150'],
            'phones.*.phone_number' => ['required', 'string', 'max:30'],
            'bank_accounts' => ['required', 'array', 'min:1'],
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:150'],
            'bank_accounts.*.branch_name' => ['nullable', 'string', 'max:150'],
            'bank_accounts.*.account_title' => ['nullable', 'string', 'max:150'],
            'bank_accounts.*.iban_account_number' => ['nullable', 'string', 'max:64'],
            'documents' => ['nullable', 'array'],
            'documents.profile_photo' => ['nullable'],
            'documents.card_front' => ['nullable'],
            'documents.card_back' => ['nullable'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }
}
