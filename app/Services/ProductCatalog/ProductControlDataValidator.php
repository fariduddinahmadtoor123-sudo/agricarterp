<?php

namespace App\Services\ProductCatalog;

use App\Models\ProductControl;
use App\Rules\UniqueProductControlName;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductControlDataValidator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, ?ProductControl $control = null): void
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:500', new UniqueProductControlName($control)],
            'control_type' => [
                'required',
                Rule::in([
                    ProductControl::TYPE_WARRANTY,
                    ProductControl::TYPE_GUARANTEE,
                    ProductControl::TYPE_RETURN_POLICY,
                    ProductControl::TYPE_REPLACEMENT_POLICY,
                    ProductControl::TYPE_HANDLING_ALERT,
                    ProductControl::TYPE_USAGE_NOTE,
                    ProductControl::TYPE_WARNING,
                ]),
            ],
            'status' => [
                'nullable',
                Rule::in([ProductControl::STATUS_ACTIVE, ProductControl::STATUS_ARCHIVED]),
            ],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }
}
