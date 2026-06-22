<?php

namespace App\Rules;

use App\Models\Unit;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueUnitAbbreviation implements ValidationRule
{
    public function __construct(protected ?Unit $ignore = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (Unit::query()
            ->whereNormalizedAbbreviation($value)
            ->when($this->ignore !== null, fn ($query) => $query->where('id', '!=', $this->ignore->id))
            ->exists()) {
            $fail('This abbreviation is already in use.');
        }
    }
}
