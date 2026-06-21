<div
    class="agricart-notifications"
    x-data="{ open: false }"
    x-on:keydown.escape.window="open = false"
>
    <button
        type="button"
        class="agricart-notifications-btn"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open.toString()"
        aria-haspopup="true"
        aria-controls="agricart-notifications-panel"
        aria-label="Notifications"
    >
        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedBell, size: \Filament\Support\Enums\IconSize::Large) }}
        <span
            class="agricart-notifications-badge"
            data-count="0"
            hidden
            aria-hidden="true"
        ></span>
    </button>

    <div
        id="agricart-notifications-panel"
        class="agricart-notifications-panel"
        x-show="open"
        x-transition.opacity.duration.150ms
        x-cloak
        x-on:click.outside="open = false"
        role="dialog"
        aria-label="Notifications"
    >
        <div class="agricart-notifications-panel__header">
            <h2 class="agricart-notifications-panel__title">Notifications</h2>
            <span class="agricart-notifications-panel__phase">Phase 2</span>
        </div>
        <p class="agricart-notifications-panel__empty">
            Notifications will appear here.
        </p>
    </div>
</div>
