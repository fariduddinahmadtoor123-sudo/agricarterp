<?php

namespace App\Services\Ai;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class CatalogEnrichmentDetector
{
    /**
     * @return list<string>
     */
    public function emptyCategoryFields(Category $category): array
    {
        return $this->emptyFields($category, config('ai.enrichment.category_fields', []));
    }

    /**
     * @return list<string>
     */
    public function emptyProductFields(Product $product): array
    {
        return $this->emptyFields($product, config('ai.enrichment.product_fields', []));
    }

    public function categoryNeedsEnrichment(Category $category): bool
    {
        if (! $this->hasEnglishName($category->name_en)) {
            return false;
        }

        if ($category->ai_status === Category::AI_STATUS_PROCESSING) {
            return false;
        }

        if (in_array($category->ai_status, [Category::AI_STATUS_PENDING, Category::AI_STATUS_FAILED], true)) {
            return true;
        }

        return $this->emptyCategoryFields($category) !== [];
    }

    public function productNeedsEnrichment(Product $product): bool
    {
        if (! $this->hasEnglishName($product->name_en)) {
            return false;
        }

        if ($product->ai_status === Product::AI_STATUS_PROCESSING) {
            return false;
        }

        if (in_array($product->ai_status, [Product::AI_STATUS_PENDING, Product::AI_STATUS_FAILED], true)) {
            return true;
        }

        return $this->emptyProductFields($product) !== [];
    }

    /**
     * @return Builder<Category>
     */
    public function pendingCategoriesQuery(?int $limit = null): Builder
    {
        $query = Category::query()
            ->where('status', Category::STATUS_ACTIVE)
            ->whereNotNull('name_en')
            ->where('name_en', '!=', '')
            ->where('ai_status', '!=', Category::AI_STATUS_PROCESSING)
            ->where(function (Builder $builder): void {
                $builder
                    ->whereIn('ai_status', [Category::AI_STATUS_PENDING, Category::AI_STATUS_FAILED])
                    ->orWhere(function (Builder $inner): void {
                        $this->applyEmptyFieldConditions($inner, config('ai.enrichment.category_fields', []));
                    });
            })
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query;
    }

    /**
     * @return Builder<Product>
     */
    public function pendingProductsQuery(?int $limit = null): Builder
    {
        $query = Product::query()
            ->where('status', Product::STATUS_ACTIVE)
            ->whereNotNull('name_en')
            ->where('name_en', '!=', '')
            ->where('ai_status', '!=', Product::AI_STATUS_PROCESSING)
            ->where(function (Builder $builder): void {
                $builder
                    ->whereIn('ai_status', [Product::AI_STATUS_PENDING, Product::AI_STATUS_FAILED])
                    ->orWhere(function (Builder $inner): void {
                        $this->applyEmptyFieldConditions($inner, config('ai.enrichment.product_fields', []));
                    });
            })
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query;
    }

    /**
     * @param  list<string>  $fields
     */
    protected function applyEmptyFieldConditions(Builder $builder, array $fields): void
    {
        foreach ($fields as $field) {
            $builder->orWhereNull($field)->orWhere($field, '');
        }
    }

    /**
     * @param  list<string>  $fields
     * @return list<string>
     */
    protected function emptyFields(object $model, array $fields): array
    {
        $empty = [];

        foreach ($fields as $field) {
            if (! $this->fieldHasValue($model->{$field} ?? null)) {
                $empty[] = $field;
            }
        }

        return $empty;
    }

    protected function fieldHasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    protected function hasEnglishName(?string $name): bool
    {
        return is_string($name) && trim($name) !== '';
    }
}
