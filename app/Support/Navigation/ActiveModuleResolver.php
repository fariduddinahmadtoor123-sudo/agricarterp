<?php

namespace App\Support\Navigation;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ModuleWorkspace;
use Illuminate\Support\Facades\Route;

class ActiveModuleResolver
{
    public function __construct(
        protected ModulePageRegistry $pageRegistry,
        protected NestedModuleNavigation $nestedNavigation,
    ) {}

    public function resolve(): ?array
    {
        $moduleKey = $this->resolveModuleKey();

        if ($moduleKey === null) {
            return null;
        }

        if ($moduleKey === 'dashboard') {
            return [
                'key' => 'dashboard',
                'label' => config('agricart.dashboard.label'),
                'submenus' => config('agricart.dashboard.submenus', []),
            ];
        }

        $module = config("agricart.modules.{$moduleKey}");

        if ($module === null) {
            return null;
        }

        return [
            'key' => $moduleKey,
            ...$module,
        ];
    }

    public function resolveModuleKey(): ?string
    {
        $route = Route::current();

        if ($route === null) {
            return null;
        }

        $controller = $route->getControllerClass();

        if ($controller === Dashboard::class) {
            return 'dashboard';
        }

        if ($controller === ModuleWorkspace::class) {
            $module = $route->parameter('module');

            if (is_string($module) && $module !== '') {
                if ($module === 'dashboard' || array_key_exists($module, config('agricart.modules', []))) {
                    return $module;
                }
            }
        }

        $resolved = $this->pageRegistry->resolveFromController($controller);

        if ($resolved !== null) {
            return $resolved['module'];
        }

        return null;
    }

    public function resolveCategoryKey(): ?string
    {
        $resolved = $this->resolveFromPageRegistry();

        return $resolved['category'] ?? null;
    }

    public function resolveTypeKey(): ?string
    {
        $resolved = $this->resolveFromPageRegistry();

        return $resolved['type'] ?? null;
    }

    public function resolveSubmenuKey(): ?string
    {
        $route = Route::current();

        if ($route === null) {
            return null;
        }

        if ($route->getControllerClass() === ModuleWorkspace::class) {
            $submenu = $route->parameter('submenu');

            return is_string($submenu) && $submenu !== '' ? $submenu : null;
        }

        $resolved = $this->resolveFromPageRegistry();

        if ($resolved !== null) {
            return $resolved['submenu'];
        }

        if ($route->getControllerClass() === Dashboard::class) {
            return 'overview';
        }

        return null;
    }

    public function hasNestedNavigation(): bool
    {
        $module = $this->resolve();

        return $module !== null && $this->nestedNavigation->hasNestedNavigation($module);
    }

    public function getSubmenus(): array
    {
        $module = $this->resolve();

        if ($module === null) {
            return [];
        }

        if ($this->nestedNavigation->hasNestedNavigation($module)) {
            $submenus = [];

            foreach ($module['categories'] ?? [] as $key => $category) {
                $submenus[$key] = $category['label'] ?? ucfirst($key);
            }

            return $submenus;
        }

        return $module['submenus'] ?? [];
    }

    public function getSubmenuLabel(?string $submenuKey = null): ?string
    {
        $moduleKey = $this->resolveModuleKey();

        if ($moduleKey === null) {
            return null;
        }

        if ($this->hasNestedNavigation()) {
            $categoryKey = $this->resolveCategoryKey();
            $typeKey = $this->resolveTypeKey();

            if ($typeKey !== null) {
                return $this->nestedNavigation->getTypeLabel($moduleKey, $categoryKey, $typeKey);
            }

            if ($categoryKey !== null) {
                return $this->nestedNavigation->getCategoryLabel($moduleKey, $categoryKey);
            }

            return null;
        }

        $submenuKey ??= $this->resolveSubmenuKey();

        if ($submenuKey === null) {
            return null;
        }

        $submenus = $this->getSubmenus();

        return $submenus[$submenuKey] ?? null;
    }

    public function isProfilePage(): bool
    {
        return Route::current()?->getControllerClass() === EditProfile::class;
    }

    /**
     * @return array{module: string, category?: string, type?: string|null, submenu: string}|null
     */
    protected function resolveFromPageRegistry(): ?array
    {
        return $this->pageRegistry->resolveFromController(Route::current()?->getControllerClass());
    }
}
