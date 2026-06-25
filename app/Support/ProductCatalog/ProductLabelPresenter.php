<?php

namespace App\Support\ProductCatalog;

use App\Models\Product;
use App\Services\ProductCatalog\ProductControlAssignmentService;
use App\Services\ProductCatalog\ProductImageStorage;
use App\Services\ProductCatalog\ProductLabelQrGenerator;
use Illuminate\Support\Str;

class ProductLabelPresenter
{
    public function __construct(
        protected ProductImageStorage $imageStorage,
        protected ProductLabelQrGenerator $qrGenerator,
        protected ProductControlAssignmentService $controlAssignment,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(Product $product): array
    {
        $product->loadMissing([
            'category',
            'brand',
            'baseUnit',
            'packingUnit',
            'images',
            'attributeValues.attribute',
            'controlGroups',
            'individualControls',
            'categoryTags',
        ]);

        $mainImage = $product->images->firstWhere('is_main', true);
        $base = $product->baseUnit?->abbreviation_en ?? $product->baseUnit?->name_en ?? '';
        $pack = $product->packingUnit?->abbreviation_en ?? $product->packingUnit?->name_en ?? '';

        $attributes = $product->attributeValues
            ->map(fn ($row): array => [
                'name' => $row->attribute?->name ?? 'Attribute',
                'value' => $row->value,
            ])
            ->all();

        $controls = $this->controlAssignment->effectiveControlLabels(
            $product->controlGroups->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $product->individualControls->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );

        return [
            'product_number' => $product->product_number,
            'name_en' => $product->name_en,
            'brand' => $product->brand?->name_en,
            'category_path' => $product->category?->full_path,
            'category_name' => $product->category?->name_en,
            'packing' => trim($product->packing_value . ' ' . $base . ' / ' . $pack),
            'base_unit' => trim($base),
            'packing_unit' => trim($pack),
            'image_url' => $this->imageStorage->url($mainImage?->image_path),
            'qr_url' => $this->qrGenerator->url($product->product_number),
            'attributes' => $attributes,
            'controls' => $controls,
            'display_tags' => $product->categoryTags
                ->pluck('name_en')
                ->filter()
                ->values()
                ->all(),
            'attribute_line' => $this->attributeLine($attributes),
            'controls_line' => $this->controlsLine($controls),
        ];
    }

    public function html(Product $product): string
    {
        return view('filament.product-catalog.product-label', [
            'label' => $this->data($product),
        ])->render();
    }

    public function attributeLine(array $attributes): ?string
    {
        if ($attributes === []) {
            return null;
        }

        $parts = [];

        foreach ($attributes as $attribute) {
            $parts[] = $attribute['name'] . ': ' . $attribute['value'];
        }

        return Str::limit(implode(' · ', $parts), 180);
    }

    public function controlsLine(array $controls): ?string
    {
        if ($controls === []) {
            return null;
        }

        return Str::limit(implode(' · ', $controls), 220);
    }
}
