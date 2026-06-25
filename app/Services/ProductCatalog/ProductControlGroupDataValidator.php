<?php

namespace App\Services\ProductCatalog;

use App\Models\ProductControlGroup;
use App\Rules\UniqueProductControlGroupName;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductControlGroupDataValidator
{
    public function __construct(
        protected ProductControlQuery $controlQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data, ?ProductControlGroup $group = null): void
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:200', new UniqueProductControlGroupName($group)],
            'control_ids' => ['required', 'array', 'min:1'],
            'control_ids.*' => ['integer', 'exists:product_controls,id'],
            'status' => [
                'nullable',
                Rule::in([ProductControlGroup::STATUS_ACTIVE, ProductControlGroup::STATUS_ARCHIVED]),
            ],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $controlIds = array_map(fn ($id): int => (int) $id, $data['control_ids'] ?? []);
        $assignable = $this->controlQuery->filterAssignableIds($controlIds);

        if (count($assignable) !== count(array_unique($controlIds))) {
            throw ValidationException::withMessages([
                'control_ids' => 'One or more selected controls are invalid or archived.',
            ]);
        }
    }
}
