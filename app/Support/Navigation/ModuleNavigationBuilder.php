<?php

namespace App\Support\Navigation;

class ModuleNavigationBuilder
{
    public function __construct(
        protected ActiveModuleResolver $moduleResolver,
        protected ModulePageRegistry $pageRegistry,
        protected NestedModuleNavigation $nestedNavigation,
    ) {}

    /**
     * @return array<int, array{key: string, label: string, url: string, active: bool, icon: mixed}>
     */
    public function primaryItems(): array
    {
        $module = $this->moduleResolver->resolve();

        if ($module === null) {
            return [];
        }

        $moduleKey = $module['key'];

        if ($this->nestedNavigation->hasNestedNavigation($module)) {
            return $this->nestedNavigation->categoryItems(
                $moduleKey,
                $this->moduleResolver->resolveCategoryKey(),
            );
        }

        return $this->flatItems($module);
    }

    /**
     * @return array<int, array{key: string, label: string, url: string, active: bool, icon: mixed}>
     */
    public function secondaryItems(): array
    {
        $module = $this->moduleResolver->resolve();

        if ($module === null || ! $this->nestedNavigation->hasNestedNavigation($module)) {
            return [];
        }

        return $this->nestedNavigation->typeItems(
            $module['key'],
            $this->moduleResolver->resolveCategoryKey(),
            $this->moduleResolver->resolveTypeKey(),
        );
    }

    /**
     * @param  array<string, mixed>  $module
     * @return array<int, array{key: string, label: string, url: string, active: bool, icon: mixed}>
     */
    protected function flatItems(array $module): array
    {
        $moduleKey = $module['key'];
        $submenus = $module['submenus'] ?? [];
        $submenuIcons = $module['submenu_icons'] ?? [];
        $activeSubmenu = $this->moduleResolver->resolveSubmenuKey();
        $items = [];

        foreach ($submenus as $key => $label) {
            $items[] = [
                'key' => $key,
                'label' => $label,
                'url' => $this->pageRegistry->submenuUrl($moduleKey, $key),
                'active' => $activeSubmenu === $key,
                'icon' => $submenuIcons[$key] ?? null,
            ];
        }

        return $items;
    }
}
