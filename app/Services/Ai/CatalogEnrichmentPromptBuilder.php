<?php

namespace App\Services\Ai;

use App\Models\Category;
use App\Models\Product;

class CatalogEnrichmentPromptBuilder
{
    /**
     * @param  list<string>  $emptyFields
     * @return array<int, array<string, mixed>>
     */
    public function buildCategoryMessages(Category $category, array $emptyFields, ?string $imageDataUri): array
    {
        $context = [
            'type' => 'category',
            'english_name' => $category->name_en,
            'category_path' => $category->full_path,
            'parent_name' => $category->parent?->name_en,
            'fields_to_fill' => $emptyFields,
        ];

        return $this->buildMessages($context, $imageDataUri);
    }

    /**
     * @param  list<string>  $emptyFields
     * @return array<int, array<string, mixed>>
     */
    public function buildProductMessages(Product $product, array $emptyFields, ?string $imageDataUri): array
    {
        $product->loadMissing(['category', 'brand']);

        $context = [
            'type' => 'product',
            'english_name' => $product->name_en,
            'category_name' => $product->category?->name_en,
            'brand_name' => $product->brand?->name_en,
            'fields_to_fill' => $emptyFields,
        ];

        return $this->buildMessages($context, $imageDataUri);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    protected function buildMessages(array $context, ?string $imageDataUri): array
    {
        $fields = implode(', ', $context['fields_to_fill']);
        $requiresUrduName = in_array('name_ur', $context['fields_to_fill'], true);

        $urduNameRule = $requiresUrduName
            ? "\n- name_ur is REQUIRED. Write the Urdu translation of the English name in proper Urdu script (not Roman Urdu). Keep it concise and accurate for shop staff."
            : '';

        $instructions = <<<TEXT
You are helping an agriculture and machinery ERP catalog.

Given the English product/category name and optional image, fill ONLY the requested empty fields.

Rules:
- Return valid JSON only.
- Include only keys from fields_to_fill.
- name_ur must be accurate Urdu in proper script.{$urduNameRule}
- hs_code should be a realistic Pakistan customs HS code when possible, otherwise your best estimate.
- Keep descriptions practical for wholesale/retail staff.
- Do not invent brand names that were not provided.
- If unsure about a field, still provide your best effort.

Requested fields: {$fields}
TEXT;

        $userContent = [
            [
                'type' => 'text',
                'text' => $instructions . "\n\nContext:\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ],
        ];

        if ($imageDataUri !== null) {
            $userContent[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $imageDataUri,
                ],
            ];
        }

        return [
            [
                'role' => 'system',
                'content' => 'You generate structured catalog metadata for an agriculture ERP. Respond with JSON only.',
            ],
            [
                'role' => 'user',
                'content' => $userContent,
            ],
        ];
    }
}
