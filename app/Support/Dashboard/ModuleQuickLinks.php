<?php

namespace App\Support\Dashboard;

use App\Support\Navigation\ModulePageRegistry;

class ModuleQuickLinks
{
    /**
     * @return array<int, array{key: string, label: string, url: string, icon: mixed}>
     */
    public static function all(): array
    {
        $registry = app(ModulePageRegistry::class);
        $links = [];

        foreach (config('agricart.modules', []) as $key => $module) {
            $links[] = [
                'key' => $key,
                'label' => $module['label'],
                'url' => $registry->moduleEntryUrl($key),
                'icon' => $module['icon'] ?? null,
            ];
        }

        return $links;
    }
}
