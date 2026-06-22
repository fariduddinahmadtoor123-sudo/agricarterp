<?php

namespace App\Rules;

use App\Models\ProductControlGroup;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueProductControlGroupName implements ValidationRule
{
    public function __construct(protected ?ProductControlGroup $ignore = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (ProductControlGroup::query()
            ->whereNormalizedName($value)
            ->when($this->ignore !== null, fn ($query) => $query->where('id', '!=', $this->ignore->id))
            ->exists()) {
            $fail('A control group with this name already exists.');
        }
    }
}
