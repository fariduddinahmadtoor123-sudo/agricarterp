@php
    $builder = app(\App\Support\Navigation\ModuleNavigationBuilder::class);
    $module = app(\App\Support\Navigation\ActiveModuleResolver::class)->resolve();
    $primaryItems = $builder->primaryItems();
    $secondaryItems = $builder->secondaryItems();
    $moduleKey = $module['key'] ?? null;
@endphp

@if (filled($primaryItems) && $module && $moduleKey !== 'dashboard')
    <div @class(['agricart-module-nav', 'agricart-module-nav--nested' => filled($secondaryItems)]) aria-label="Module navigation">
        <div class="agricart-module-nav__inner">
            <span class="agricart-module-nav__module">{{ $module['label'] }}</span>
            @include('filament.layout.partials.module-nav-items', ['items' => $primaryItems])
        </div>

        @if (filled($secondaryItems))
            <div class="agricart-module-nav__secondary">
                @include('filament.layout.partials.module-nav-items', [
                    'items' => $secondaryItems,
                    'secondary' => true,
                ])
            </div>
        @endif
    </div>
@endif
