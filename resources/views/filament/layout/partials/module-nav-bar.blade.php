@php
    $builder = app(\App\Support\Navigation\ModuleNavigationBuilder::class);
    $module = app(\App\Support\Navigation\ActiveModuleResolver::class)->resolve();
    $primaryItems = $builder->primaryItems();
    $secondaryItems = $builder->secondaryItems();
@endphp

@if (filled($primaryItems) && $module)
    <div @class(['agricart-module-nav', 'agricart-module-nav--nested' => filled($secondaryItems)]) aria-label="Module navigation">
        <div class="agricart-module-nav__inner">
            <span class="agricart-module-nav__module">{{ $module['label'] }}</span>
            <div class="agricart-module-nav__scroll">
                <ul class="agricart-module-nav__list">
                    @foreach ($primaryItems as $item)
                        <li>
                            <a
                                href="{{ $item['url'] }}"
                                @class([
                                    'agricart-module-nav__link',
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
        </div>

        @if (filled($secondaryItems))
            <div class="agricart-module-nav__secondary">
                <div class="agricart-module-nav__scroll">
                    <ul class="agricart-module-nav__list">
                        @foreach ($secondaryItems as $item)
                            <li>
                                <a
                                    href="{{ $item['url'] }}"
                                    @class([
                                        'agricart-module-nav__link',
                                        'agricart-module-nav__link--secondary',
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
            </div>
        @endif
    </div>
@endif
