<?php

namespace App\Filament\Contacts\Support;

use App\Filament\Pages\Contacts\Customers;
use App\Filament\Pages\Contacts\Suppliers;
use Filament\Actions\Action;
use Filament\Schemas\Components\Group;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\View\TablesRenderHook;

class ContactsListToolbar
{
    public static function register(): void
    {
        foreach ([Suppliers::class, Customers::class] as $scope) {
            FilamentView::registerRenderHook(
                TablesRenderHook::TOOLBAR_SEARCH_AFTER,
                fn (): string => '<span data-agricart-primary-filters-anchor class="agricart-contacts-primary-filters-anchor"></span>',
                $scope,
            );
        }
    }

    /**
     * @param  array<string, Group>  $filters
     * @param  list<string>  $keys
     * @param  array<string, string>  $wrapperClasses
     */
    public static function primaryFiltersGroup(array $filters, array $keys, array $wrapperClasses = []): Group
    {
        $components = [];

        foreach ($keys as $key) {
            $group = $filters[$key] ?? null;

            if ($group === null) {
                continue;
            }

            if (filled($wrapperClasses[$key] ?? null)) {
                $group = $group->extraAttributes([
                    'class' => $wrapperClasses[$key],
                ]);
            }

            $components[] = $group;
        }

        return Group::make()
            ->schema($components)
            ->columnSpanFull()
            ->extraAttributes([
                'class' => 'agricart-contacts-primary-filters',
                'x-data' => '{}',
                'x-init' => <<<'JS'
                    $nextTick(() => {
                        const root = $el.closest('.agricart-contacts-list');
                        const anchor = root?.querySelector('[data-agricart-primary-filters-anchor]');

                        if (anchor) {
                            anchor.appendChild($el);
                        }
                    });
                JS,
            ]);
    }

    /**
     * @param  array<string, Group>  $filters
     * @param  list<string>  $keys
     * @return list<Group>
     */
    public static function moreFilterComponents(array $filters, array $keys): array
    {
        return array_values(array_filter(
            array_map(fn (string $key): ?Group => $filters[$key] ?? null, $keys),
        ));
    }

    public static function configureMoreFiltersTrigger(Action $action): Action
    {
        return $action
            ->label('More Filters')
            ->icon(Heroicon::OutlinedChevronDown)
            ->iconPosition(IconPosition::After)
            ->button()
            ->outlined()
            ->color('gray');
    }
}
