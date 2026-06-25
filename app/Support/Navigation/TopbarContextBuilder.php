<?php

namespace App\Support\Navigation;

use App\Filament\Pages\Dashboard;

class TopbarContextBuilder
{
    public function __construct(
        protected BreadcrumbBuilder $breadcrumbBuilder,
        protected ActiveModuleResolver $moduleResolver,
    ) {}

    public function pageTitle(): string
    {
        $breadcrumbs = $this->breadcrumbBuilder->build();

        if ($breadcrumbs === []) {
            return config('agricart.dashboard.label', 'Dashboard');
        }

        $last = $breadcrumbs[array_key_last($breadcrumbs)];

        return is_string($last) ? $last : config('agricart.dashboard.label', 'Dashboard');
    }

    public function moduleLabel(): ?string
    {
        $moduleKey = $this->moduleResolver->resolveModuleKey();

        if ($moduleKey === null || $moduleKey === 'dashboard') {
            return null;
        }

        $module = $this->moduleResolver->resolve();

        return $module['label'] ?? null;
    }

    public function isDashboardHome(): bool
    {
        return request()->route()?->getControllerClass() === Dashboard::class;
    }
}
