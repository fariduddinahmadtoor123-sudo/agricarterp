<div class="agricart-controls-tabs" role="tablist" aria-label="Product controls lists">
    <button
        type="button"
        role="tab"
        wire:click="$set('activeList', 'controls')"
        @class(['agricart-controls-tabs__tab', 'agricart-controls-tabs__tab--active' => $activeList === 'controls'])
        aria-selected="{{ $activeList === 'controls' ? 'true' : 'false' }}"
    >
        Controls
    </button>
    <button
        type="button"
        role="tab"
        wire:click="$set('activeList', 'groups')"
        @class(['agricart-controls-tabs__tab', 'agricart-controls-tabs__tab--active' => $activeList === 'groups'])
        aria-selected="{{ $activeList === 'groups' ? 'true' : 'false' }}"
    >
        Groups
    </button>
</div>
