<?php

namespace App\Support\Navigation;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ModuleWorkspace;

class ModulePageRegistry
{
    /**
     * @return array{module: string, category?: string, type?: string|null, submenu: string}|null
     */
    public function resolveFromController(?string $controller): ?array
    {
        if ($controller === null) {
            return null;
        }

        foreach (config('agricart.modules', []) as $moduleKey => $module) {
            foreach ($module['pages'] ?? [] as $pageKey => $pageClass) {
                if ($controller !== $pageClass) {
                    continue;
                }

                if (str_contains($pageKey, '.')) {
                    [$category, $type] = explode('.', $pageKey, 2);

                    return [
                        'module' => $moduleKey,
                        'category' => $category,
                        'type' => $type,
                        'submenu' => $type,
                    ];
                }

                if ($this->moduleHasCategories($moduleKey)) {
                    return [
                        'module' => $moduleKey,
                        'category' => $pageKey,
                        'type' => null,
                        'submenu' => $pageKey,
                    ];
                }

                return [
                    'module' => $moduleKey,
                    'submenu' => $pageKey,
                ];
            }
        }

        return null;
    }

    public function submenuUrl(string $moduleKey, string $submenuKey): string
    {
        if ($this->moduleHasCategories($moduleKey)) {
            return $this->nestedPageUrl($moduleKey, $submenuKey);
        }

        $pageClass = config("agricart.modules.{$moduleKey}.pages.{$submenuKey}");

        if (is_string($pageClass) && class_exists($pageClass)) {
            return $pageClass::getUrl();
        }

        if ($moduleKey === 'dashboard' && $submenuKey === 'overview') {
            return Dashboard::getUrl();
        }

        return ModuleWorkspace::getUrl([
            'module' => $moduleKey,
            'submenu' => $submenuKey,
        ]);
    }

    public function nestedPageUrl(string $moduleKey, string $categoryKey, ?string $typeKey = null): string
    {
        $pages = config("agricart.modules.{$moduleKey}.pages", []);

        if ($typeKey !== null) {
            $pageClass = $pages["{$categoryKey}.{$typeKey}"] ?? null;

            if (is_string($pageClass) && class_exists($pageClass)) {
                return $pageClass::getUrl();
            }

            return ModuleWorkspace::getUrl([
                'module' => $moduleKey,
                'submenu' => "{$categoryKey}.{$typeKey}",
            ]);
        }

        $pageClass = $pages[$categoryKey] ?? null;

        if (is_string($pageClass) && class_exists($pageClass)) {
            return $pageClass::getUrl();
        }

        $types = config("agricart.modules.{$moduleKey}.categories.{$categoryKey}.types", []);
        $firstType = array_key_first($types);

        if (is_string($firstType)) {
            return $this->nestedPageUrl($moduleKey, $categoryKey, $firstType);
        }

        return ModuleWorkspace::getUrl([
            'module' => $moduleKey,
            'submenu' => $categoryKey,
        ]);
    }

    public function moduleEntryUrl(string $moduleKey): string
    {
        if ($this->moduleHasCategories($moduleKey)) {
            return $this->nestedPageUrl($moduleKey, 'overview');
        }

        $submenus = config("agricart.modules.{$moduleKey}.submenus", []);
        $firstSubmenu = array_key_first($submenus);

        if ($firstSubmenu === null) {
            return ModuleWorkspace::getUrl(['module' => $moduleKey]);
        }

        return $this->submenuUrl($moduleKey, $firstSubmenu);
    }

    public function moduleHasCategories(string $moduleKey): bool
    {
        return filled(config("agricart.modules.{$moduleKey}.categories"));
    }
}
