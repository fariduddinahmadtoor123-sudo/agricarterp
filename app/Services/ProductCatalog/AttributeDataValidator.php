<?php

namespace App\Services\ProductCatalog;

use App\Models\Attribute;
use App\Rules\UniqueAttributeName;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttributeDataValidator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, ?Attribute $attribute = null): void
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:100', new UniqueAttributeName($attribute)],
            'status' => [
                'nullable',
                Rule::in([Attribute::STATUS_ACTIVE, Attribute::STATUS_ARCHIVED]),
            ],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }
}
