<?php

namespace App\Rules;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueProductEnglishNamePerBrand implements ValidationRule
{
    public function __construct(
        protected int | string | null $brandId,
        protected ?Product $ignore = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '' || blank($this->brandId)) {
            return;
        }

        if (Product::query()
            ->where('brand_id', (int) $this->brandId)
            ->whereNormalizedEnglishName($value)
            ->when($this->ignore !== null, fn ($query) => $query->where('id', '!=', $this->ignore->id))
            ->exists()) {
            $fail('A product with this name already exists for the selected brand.');
        }
    }
}
