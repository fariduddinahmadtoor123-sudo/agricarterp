<?php

namespace App\Rules;

use App\Models\Brand;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueBrandEnglishName implements ValidationRule
{
    public function __construct(protected ?Brand $ignore = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (Brand::query()
            ->whereNormalizedEnglishName($value)
            ->when($this->ignore !== null, fn ($query) => $query->where('id', '!=', $this->ignore->id))
            ->exists()) {
            $fail('This brand name already exists.');
        }
    }
}
