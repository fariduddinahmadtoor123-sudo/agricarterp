@php
    $modules = \App\Support\Dashboard\ModuleQuickLinks::all();
@endphp

<div class="agricart-dashboard-links">
    @foreach ($modules as $module)
        <a href="{{ $module['url'] }}" class="agricart-dashboard-links__card">
            @if (filled($module['icon'] ?? null))
                <span class="agricart-dashboard-links__icon">
                    {{ \Filament\Support\generate_icon_html($module['icon'], size: \Filament\Support\Enums\IconSize::Large) }}
                </span>
            @endif
            <span class="agricart-dashboard-links__label">{{ $module['label'] }}</span>
        </a>
    @endforeach
</div>
