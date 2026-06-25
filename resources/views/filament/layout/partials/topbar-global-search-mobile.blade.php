<button
    type="button"
    class="agricart-global-search__mobile-trigger"
    x-data
    x-on:click="window.dispatchEvent(new CustomEvent('open-agricart-search'))"
    aria-label="Open search"
>
    {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedMagnifyingGlass, size: \Filament\Support\Enums\IconSize::Large) }}
</button>
