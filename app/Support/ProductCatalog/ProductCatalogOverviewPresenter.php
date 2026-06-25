<?php

namespace App\Support\ProductCatalog;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductControl;
use App\Models\Unit;
use App\Support\Navigation\ModulePageRegistry;
use Filament\Support\Icons\Heroicon;

class ProductCatalogOverviewPresenter
{
    public function __construct(
        protected ModulePageRegistry $pageRegistry,
    ) {}

    /**
     * @return list<array{key: string, label: string, value: int, hint: string, icon: mixed, tone?: string}>
     */
    public function stats(): array
    {
        return [
            [
                'key' => 'active_products',
                'label' => 'Active Products',
                'value' => Product::query()->active()->count(),
                'hint' => 'Visible in lists, labels, and storefront',
                'icon' => Heroicon::OutlinedCube,
            ],
            [
                'key' => 'archived_products',
                'label' => 'Archived Products',
                'value' => Product::query()->archived()->count(),
                'hint' => 'Preserved records kept out of active use',
                'icon' => Heroicon::OutlinedArchiveBox,
                'tone' => 'muted',
            ],
            [
                'key' => 'categories',
                'label' => 'Active Categories',
                'value' => Category::query()->active()->count(),
                'hint' => 'Hierarchy nodes available for assignment',
                'icon' => Heroicon::OutlinedRectangleStack,
            ],
            [
                'key' => 'brands',
                'label' => 'Active Brands',
                'value' => Brand::query()->active()->count(),
                'hint' => 'Brand masters linked to products',
                'icon' => Heroicon::OutlinedTag,
            ],
            [
                'key' => 'units',
                'label' => 'Active Units',
                'value' => Unit::query()->active()->count(),
                'hint' => 'Base and packing units in use',
                'icon' => Heroicon::OutlinedScale,
            ],
            [
                'key' => 'attributes',
                'label' => 'Active Attributes',
                'value' => Attribute::query()->active()->count(),
                'hint' => 'Specification fields for products',
                'icon' => Heroicon::OutlinedAdjustmentsHorizontal,
            ],
            [
                'key' => 'controls',
                'label' => 'Active Controls',
                'value' => ProductControl::query()->active()->count(),
                'hint' => 'Compliance and handling rules',
                'icon' => Heroicon::OutlinedShieldCheck,
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, description: string, url: string, icon: mixed}>
     */
    public function quickLinks(): array
    {
        $moduleKey = 'product-catalog';
        $icons = config("agricart.modules.{$moduleKey}.submenu_icons", []);
        $labels = config("agricart.modules.{$moduleKey}.submenus", []);
        $descriptions = [
            'products' => 'Create and maintain catalog products',
            'categories' => 'Manage multi-level category hierarchy',
            'brands' => 'Maintain brand master records',
            'units' => 'Configure units and packing rules',
            'attributes' => 'Define reusable product attributes',
            'controls' => 'Manage controls and control groups',
            'labels' => 'Print product price-tag labels',
        ];
        $links = [];

        foreach ($labels as $key => $label) {
            if ($key === 'overview') {
                continue;
            }

            $links[] = [
                'key' => $key,
                'label' => $label,
                'description' => $descriptions[$key] ?? 'Open module',
                'url' => $this->pageRegistry->submenuUrl($moduleKey, $key),
                'icon' => $icons[$key] ?? Heroicon::OutlinedCube,
            ];
        }

        return $links;
    }
}
