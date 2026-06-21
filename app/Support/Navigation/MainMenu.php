<?php

namespace App\Support\Navigation;

use Filament\Navigation\NavigationItem;

class MainMenu
{
    /**
     * @return array<NavigationItem>
     */
    public static function items(): array
    {
        $items = [];

        foreach (config('agricart.modules', []) as $key => $module) {
            $items[] = NavigationItem::make($module['label'])
                ->icon($module['icon'])
                ->sort($module['sort'])
                ->url(fn (): string => app(ModulePageRegistry::class)->moduleEntryUrl($key))
                ->isActiveWhen(function () use ($key): bool {
                    $resolver = app(ActiveModuleResolver::class);

                    return $resolver->resolveModuleKey() === $key;
                });
        }

        return $items;
    }
}
