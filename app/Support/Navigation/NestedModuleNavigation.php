<?php

namespace App\Support\Navigation;

class NestedModuleNavigation
{
    public function __construct(
        protected ModulePageRegistry $pageRegistry,
    ) {}

    public function hasNestedNavigation(?array $module): bool
    {
        return filled($module['categories'] ?? null);
    }

    /**
     * @return array<int, array{key: string, label: string, url: string, active: bool, icon: mixed}>
     */
    public function categoryItems(string $moduleKey, ?string $activeCategory): array
    {
        $categories = config("agricart.modules.{$moduleKey}.categories", []);
        $categoryIcons = config("agricart.modules.{$moduleKey}.category_icons", []);
        $items = [];

        foreach ($categories as $key => $category) {
            $items[] = [
                'key' => $key,
                'label' => $category['label'] ?? ucfirst($key),
                'url' => $this->pageRegistry->nestedPageUrl($moduleKey, $key),
                'active' => $activeCategory === $key,
                'icon' => $categoryIcons[$key] ?? null,
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array{key: string, label: string, url: string, active: bool, icon: mixed}>
     */
    public function typeItems(string $moduleKey, ?string $activeCategory, ?string $activeType): array
    {
        if ($activeCategory === null || $activeCategory === 'overview') {
            return [];
        }

        $types = config("agricart.modules.{$moduleKey}.categories.{$activeCategory}.types", []);

        if ($types === []) {
            return [];
        }

        $typeIcons = config("agricart.modules.{$moduleKey}.type_icons.{$activeCategory}", []);
        $items = [];

        foreach ($types as $key => $label) {
            $items[] = [
                'key' => $key,
                'label' => $label,
                'url' => $this->pageRegistry->nestedPageUrl($moduleKey, $activeCategory, $key),
                'active' => $activeType === $key,
                'icon' => $typeIcons[$key] ?? null,
            ];
        }

        return $items;
    }

    public function getCategoryLabel(string $moduleKey, ?string $categoryKey): ?string
    {
        if ($categoryKey === null) {
            return null;
        }

        return config("agricart.modules.{$moduleKey}.categories.{$categoryKey}.label");
    }

    public function getTypeLabel(string $moduleKey, ?string $categoryKey, ?string $typeKey): ?string
    {
        if ($categoryKey === null || $typeKey === null) {
            return null;
        }

        return config("agricart.modules.{$moduleKey}.categories.{$categoryKey}.types.{$typeKey}");
    }
}
