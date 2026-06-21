<?php

namespace App\Support\Navigation;

use App\Filament\Pages\Dashboard;

class BreadcrumbBuilder
{
    public function __construct(
        protected ActiveModuleResolver $moduleResolver,
        protected ModulePageRegistry $pageRegistry,
        protected NestedModuleNavigation $nestedNavigation,
    ) {}

    /**
     * Filament breadcrumbs use `[url => label]`; the current page uses an integer key.
     *
     * @return array<int|string, string>
     */
    public function build(): array
    {
        if ($this->moduleResolver->isProfilePage()) {
            return [
                Dashboard::getUrl() => config('agricart.dashboard.label', 'Dashboard'),
                $this->pageRegistry->submenuUrl('settings', 'overview') => config('agricart.modules.settings.label', 'Settings'),
                'Profile',
            ];
        }

        $moduleKey = $this->moduleResolver->resolveModuleKey();

        if ($moduleKey === null || $this->isMainDashboardPage()) {
            return [
                config('agricart.dashboard.label', 'Dashboard'),
            ];
        }

        $module = $this->moduleResolver->resolve();
        $moduleLabel = $module['label'] ?? ucfirst($moduleKey);

        $breadcrumbs = [
            Dashboard::getUrl() => config('agricart.dashboard.label', 'Dashboard'),
        ];

        if ($moduleKey === 'dashboard') {
            $submenuKey = $this->moduleResolver->resolveSubmenuKey();
            $submenuLabel = $this->moduleResolver->getSubmenuLabel($submenuKey);

            if ($submenuKey && $submenuKey !== 'overview' && $submenuLabel) {
                $breadcrumbs[] = $submenuLabel;
            }

            return $breadcrumbs;
        }

        if ($this->moduleResolver->hasNestedNavigation()) {
            return $this->buildNestedBreadcrumbs($moduleKey, $moduleLabel);
        }

        return $this->buildFlatBreadcrumbs($moduleKey, $moduleLabel);
    }

    /**
     * @return array<int|string, string>
     */
    protected function buildNestedBreadcrumbs(string $moduleKey, string $moduleLabel): array
    {
        $categoryKey = $this->moduleResolver->resolveCategoryKey();
        $typeKey = $this->moduleResolver->resolveTypeKey();
        $categoryLabel = $this->nestedNavigation->getCategoryLabel($moduleKey, $categoryKey);
        $typeLabel = $this->nestedNavigation->getTypeLabel($moduleKey, $categoryKey, $typeKey);

        $breadcrumbs = [
            Dashboard::getUrl() => config('agricart.dashboard.label', 'Dashboard'),
            $this->pageRegistry->nestedPageUrl($moduleKey, 'overview') => $moduleLabel,
        ];

        if ($categoryKey === null || $categoryKey === 'overview') {
            $breadcrumbs[] = $categoryLabel ?? 'Overview';

            return $breadcrumbs;
        }

        $breadcrumbs[$this->pageRegistry->nestedPageUrl($moduleKey, $categoryKey)] = $categoryLabel ?? ucfirst($categoryKey);

        if ($typeKey !== null && $typeLabel !== null) {
            $breadcrumbs[] = $typeLabel;
        }

        return $breadcrumbs;
    }

    /**
     * @return array<int|string, string>
     */
    protected function buildFlatBreadcrumbs(string $moduleKey, string $moduleLabel): array
    {
        $submenuKey = $this->moduleResolver->resolveSubmenuKey();
        $submenuLabel = $this->moduleResolver->getSubmenuLabel($submenuKey);

        $breadcrumbs = [
            Dashboard::getUrl() => config('agricart.dashboard.label', 'Dashboard'),
        ];

        $firstSubmenuKey = array_key_first(config("agricart.modules.{$moduleKey}.submenus", []));

        $moduleUrl = $this->pageRegistry->submenuUrl(
            $moduleKey,
            is_string($firstSubmenuKey) ? $firstSubmenuKey : ($submenuKey ?? ''),
        );

        if ($submenuKey && $submenuLabel) {
            $breadcrumbs[$moduleUrl] = $moduleLabel;
            $breadcrumbs[] = $submenuLabel;

            return $breadcrumbs;
        }

        $breadcrumbs[] = $moduleLabel;

        return $breadcrumbs;
    }

    protected function isMainDashboardPage(): bool
    {
        return request()->route()?->getControllerClass() === Dashboard::class;
    }
}
