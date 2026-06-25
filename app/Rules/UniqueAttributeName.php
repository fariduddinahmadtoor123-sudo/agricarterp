<?php

namespace App\Rules;

use App\Models\Attribute;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueAttributeName implements ValidationRule
{
    public function __construct(protected ?Attribute $ignore = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (Attribute::query()
            ->whereNormalizedName($value)
            ->when($this->ignore !== null, fn ($query) => $query->where('id', '!=', $this->ignore->id))
            ->exists()) {
            $fail('An attribute with this name already exists.');
        }
    }
}
