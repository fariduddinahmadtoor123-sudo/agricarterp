<?php

namespace App\Rules;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueCategoryEnglishName implements ValidationRule
{
    public function __construct(protected ?Category $ignore = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (Category::query()
            ->whereNormalizedEnglishName($value)
            ->when($this->ignore !== null, fn ($query) => $query->where('id', '!=', $this->ignore->id))
            ->exists()) {
            $fail('This category name already exists.');
        }
    }
}
