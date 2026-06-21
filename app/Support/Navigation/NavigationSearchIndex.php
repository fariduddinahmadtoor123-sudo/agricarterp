<?php

namespace App\Support\Navigation;

use App\Filament\Pages\Dashboard;

class NavigationSearchIndex
{
    public function __construct(
        protected ModulePageRegistry $pageRegistry,
    ) {}

    /**
     * @return array<int, array{
     *     id: string,
     *     label: string,
     *     breadcrumb: string,
     *     module: string,
     *     url: string,
     *     keywords: string,
     *     scopes: array<int, string>
     * }>
     */
    public function all(): array
    {
        $entries = [
            $this->makeEntry(
                id: 'dashboard.overview',
                moduleLabel: config('agricart.dashboard.label', 'Dashboard'),
                pageLabel: config('agricart.dashboard.submenus.overview', 'Overview'),
                url: Dashboard::getUrl(),
                moduleKey: 'dashboard',
            ),
        ];

        foreach (config('agricart.modules', []) as $moduleKey => $module) {
            if ($this->pageRegistry->moduleHasCategories($moduleKey)) {
                $entries = array_merge($entries, $this->nestedModuleEntries($moduleKey, $module));
            } else {
                $entries = array_merge($entries, $this->flatModuleEntries($moduleKey, $module));
            }
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $module
     * @return array<int, array{id: string, label: string, breadcrumb: string, module: string, url: string, keywords: string, scopes: array<int, string>}>
     */
    protected function flatModuleEntries(string $moduleKey, array $module): array
    {
        $moduleLabel = $module['label'] ?? ucfirst($moduleKey);
        $submenus = $module['submenus'] ?? [];
        $entries = [];

        $entries[] = $this->makeEntry(
            id: "{$moduleKey}.__module__",
            moduleLabel: $moduleLabel,
            pageLabel: $moduleLabel,
            url: $this->pageRegistry->moduleEntryUrl($moduleKey),
            moduleKey: $moduleKey,
            isModuleRoot: true,
        );

        foreach ($submenus as $submenuKey => $pageLabel) {
            $entries[] = $this->makeEntry(
                id: "{$moduleKey}.{$submenuKey}",
                moduleLabel: $moduleLabel,
                pageLabel: $pageLabel,
                url: $this->pageRegistry->submenuUrl($moduleKey, $submenuKey),
                moduleKey: $moduleKey,
                extraKeywords: [$submenuKey],
            );
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $module
     * @return array<int, array{id: string, label: string, breadcrumb: string, module: string, url: string, keywords: string, scopes: array<int, string>}>
     */
    protected function nestedModuleEntries(string $moduleKey, array $module): array
    {
        $moduleLabel = $module['label'] ?? ucfirst($moduleKey);
        $categories = $module['categories'] ?? [];
        $entries = [];

        $entries[] = $this->makeEntry(
            id: "{$moduleKey}.__module__",
            moduleLabel: $moduleLabel,
            pageLabel: $moduleLabel,
            url: $this->pageRegistry->moduleEntryUrl($moduleKey),
            moduleKey: $moduleKey,
            isModuleRoot: true,
        );

        foreach ($categories as $categoryKey => $category) {
            $categoryLabel = $category['label'] ?? ucfirst($categoryKey);
            $types = $category['types'] ?? [];

            if ($types === []) {
                $entries[] = $this->makeEntry(
                    id: "{$moduleKey}.{$categoryKey}",
                    moduleLabel: $moduleLabel,
                    pageLabel: $categoryLabel,
                    url: $this->pageRegistry->nestedPageUrl($moduleKey, $categoryKey),
                    moduleKey: $moduleKey,
                    categoryLabel: $categoryLabel,
                    extraKeywords: [$categoryKey],
                );

                continue;
            }

            foreach ($types as $typeKey => $typeLabel) {
                $entries[] = $this->makeEntry(
                    id: "{$moduleKey}.{$categoryKey}.{$typeKey}",
                    moduleLabel: $moduleLabel,
                    pageLabel: $typeLabel,
                    url: $this->pageRegistry->nestedPageUrl($moduleKey, $categoryKey, $typeKey),
                    moduleKey: $moduleKey,
                    categoryLabel: $categoryKey === 'overview' ? null : $categoryLabel,
                    extraKeywords: [$categoryKey, $typeKey],
                );
            }
        }

        return $entries;
    }

    /**
     * @param  array<int, string>  $extraKeywords
     * @return array{id: string, label: string, breadcrumb: string, module: string, url: string, keywords: string, scopes: array<int, string>}
     */
    protected function makeEntry(
        string $id,
        string $moduleLabel,
        string $pageLabel,
        string $url,
        string $moduleKey,
        ?string $categoryLabel = null,
        bool $isModuleRoot = false,
        array $extraKeywords = [],
    ): array {
        $breadcrumb = $this->breadcrumb($moduleLabel, $categoryLabel, $pageLabel, $isModuleRoot);

        return [
            'id' => $id,
            'label' => $isModuleRoot ? $moduleLabel : $pageLabel,
            'breadcrumb' => $breadcrumb,
            'module' => $moduleLabel,
            'url' => $url,
            'keywords' => $this->keywords($moduleKey, $moduleLabel, $pageLabel, $categoryLabel, $extraKeywords),
            'scopes' => $this->scopesForModule($moduleKey),
        ];
    }

    protected function breadcrumb(
        string $moduleLabel,
        ?string $categoryLabel,
        string $pageLabel,
        bool $isModuleRoot,
    ): string {
        if ($isModuleRoot) {
            return $moduleLabel;
        }

        if ($categoryLabel === null || $categoryLabel === $pageLabel) {
            return "{$moduleLabel} > {$pageLabel}";
        }

        return "{$moduleLabel} > {$categoryLabel} > {$pageLabel}";
    }

    /**
     * @param  array<int, string>  $extraKeywords
     */
    protected function keywords(
        string $moduleKey,
        string $moduleLabel,
        string $pageLabel,
        ?string $categoryLabel,
        array $extraKeywords,
    ): string {
        $parts = array_filter([
            $moduleKey,
            str_replace('-', ' ', $moduleKey),
            $moduleLabel,
            $categoryLabel,
            $pageLabel,
            ...$extraKeywords,
            ...array_map(fn (string $part): string => str_replace('-', ' ', $part), $extraKeywords),
        ]);

        return mb_strtolower(implode(' ', $parts));
    }

    /**
     * @return array<int, string>
     */
    protected function scopesForModule(string $moduleKey): array
    {
        $scopes = ['modules', 'pages'];

        return match ($moduleKey) {
            'reports-analytics' => [...$scopes, 'reports'],
            'settings' => [...$scopes, 'settings'],
            'approvals' => [...$scopes, 'approvals'],
            'documentation' => [...$scopes, 'documentation'],
            default => $scopes,
        };
    }
}
