@props([
    'items' => [],
    'secondary' => false,
])

@php
    $linkClass = $secondary
        ? 'agricart-module-nav__link agricart-module-nav__link--secondary'
        : 'agricart-module-nav__link';
@endphp

<div class="agricart-module-nav__items">
    <ul class="agricart-module-nav__list">
        @foreach ($items as $item)
            <li class="agricart-module-nav__item">
                <a
                    href="{{ $item['url'] }}"
                    @class([
                        $linkClass,
                        'agricart-module-nav__link--active' => $item['active'],
                    ])
                >
                    @if (filled($item['icon'] ?? null))
                        {{ \Filament\Support\generate_icon_html($item['icon'], size: \Filament\Support\Enums\IconSize::Small) }}
                    @endif
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</div>
