@php
    $navigationSearchIndex = app(\App\Support\Navigation\NavigationSearchIndex::class)->all();
@endphp

<div
    class="agricart-global-search"
    x-data="agricartGlobalSearch(@js($navigationSearchIndex))"
    x-on:open-agricart-search.window="openMobile()"
    x-on:keydown.escape.window="closeAll()"
>
    {{-- Desktop search --}}
    <div class="agricart-global-search__desktop">
        <label class="agricart-global-search__label" for="agricart-global-search-input">
            <span class="sr-only">Global Search</span>
            {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedMagnifyingGlass, size: \Filament\Support\Enums\IconSize::Small) }}
            <input
                id="agricart-global-search-input"
                type="search"
                class="agricart-global-search__input"
                placeholder="Search modules, pages, reports…"
                x-model="query"
                x-on:focus="open = true"
                x-on:keydown="handleKeydown($event)"
                x-on:input="resetActiveIndex()"
                autocomplete="off"
                role="combobox"
                aria-expanded="false"
                x-bind:aria-expanded="showDropdown().toString()"
                aria-controls="agricart-global-search-results"
                aria-autocomplete="list"
            />
        </label>

        <div
            id="agricart-global-search-results"
            class="agricart-global-search__dropdown"
            x-show="showDropdown()"
            x-transition.opacity.duration.150ms
            x-cloak
            x-on:click.outside="open = false"
            role="listbox"
            aria-label="Search results"
        >
            @include('filament.layout.partials.navigation-search-results')
        </div>
    </div>

    {{-- Mobile search modal --}}
    <div
        class="agricart-global-search__modal"
        x-show="mobileOpen"
        x-cloak
        role="dialog"
        aria-modal="true"
        aria-label="Global Search"
    >
        <div class="agricart-global-search__modal-backdrop" x-on:click="closeAll()"></div>
        <div class="agricart-global-search__modal-panel">
            <div class="agricart-global-search__modal-header">
                <label class="agricart-global-search__label agricart-global-search__label--modal" for="agricart-global-search-mobile">
                    {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedMagnifyingGlass, size: \Filament\Support\Enums\IconSize::Small) }}
                    <input
                        id="agricart-global-search-mobile"
                        type="search"
                        class="agricart-global-search__input"
                        placeholder="Search modules, pages, reports…"
                        x-model="query"
                        x-ref="mobileInput"
                        x-on:focus="open = true"
                        x-on:keydown="handleKeydown($event)"
                        x-on:input="resetActiveIndex()"
                        autocomplete="off"
                        role="combobox"
                        aria-expanded="false"
                        x-bind:aria-expanded="hasQuery().toString()"
                        aria-controls="agricart-global-search-results-mobile"
                        aria-autocomplete="list"
                    />
                </label>
                <button
                    type="button"
                    class="agricart-global-search__close"
                    x-on:click="closeAll()"
                    aria-label="Close search"
                >
                    {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedXMark, size: \Filament\Support\Enums\IconSize::Small) }}
                </button>
            </div>

            <div
                id="agricart-global-search-results-mobile"
                class="agricart-global-search__results agricart-global-search__results--modal"
                role="listbox"
                aria-label="Search results"
            >
                @include('filament.layout.partials.navigation-search-results')
            </div>
        </div>
    </div>
</div>
