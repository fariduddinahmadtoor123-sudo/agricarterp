@php
    $presenter = app(\App\Support\Settings\SettingsOverviewPresenter::class);
    $stats = $presenter->stats();
    $cards = $presenter->configurationCards();
    $links = $presenter->quickLinks();
@endphp

<div class="agricart-pc-overview">
    <section class="agricart-pc-overview__section" aria-labelledby="settings-overview-stats-heading">
        <header class="agricart-pc-overview__section-header">
            <h2 id="settings-overview-stats-heading" class="agricart-pc-overview__section-title">Settings Summary</h2>
            <p class="agricart-pc-overview__section-subtitle">Live counts for users, roles, backups, and configured modules.</p>
        </header>

        <div class="agricart-pc-overview__grid agricart-pc-overview__grid--stats">
            @foreach ($stats as $stat)
                <article class="agricart-pc-overview__card agricart-pc-overview__card--stat" data-stat="{{ $stat['key'] }}">
                    <div class="agricart-pc-overview__card-top">
                        <span @class([
                            'agricart-pc-overview__card-icon',
                            'agricart-pc-overview__card-icon--muted' => ($stat['tone'] ?? null) === 'muted',
                        ])>
                            {{ \Filament\Support\generate_icon_html($stat['icon'], size: \Filament\Support\Enums\IconSize::Medium) }}
                        </span>
                        <span class="agricart-pc-overview__card-value">
                            @if ($stat['key'] === 'modules_ready')
                                {{ (int) $stat['value'] }} / 4
                            @else
                                {{ number_format((int) $stat['value']) }}
                            @endif
                        </span>
                    </div>
                    <h3 class="agricart-pc-overview__card-title">{{ $stat['label'] }}</h3>
                    <p class="agricart-pc-overview__card-hint">{{ $stat['hint'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="agricart-pc-overview__section" aria-labelledby="settings-overview-config-heading">
        <header class="agricart-pc-overview__section-header">
            <h2 id="settings-overview-config-heading" class="agricart-pc-overview__section-title">Current Configuration</h2>
            <p class="agricart-pc-overview__section-subtitle">What is saved right now across store, pricing, AI, printing, access, and backups.</p>
        </header>

        <div class="agricart-pc-overview__grid agricart-pc-overview__grid--config">
            @foreach ($cards as $card)
                <a href="{{ $card['url'] }}" class="agricart-pc-overview__card agricart-pc-overview__card--config agricart-pc-overview__card--link" data-config="{{ $card['key'] }}">
                    <div class="agricart-pc-overview__card-top">
                        <span class="agricart-pc-overview__card-icon">
                            {{ \Filament\Support\generate_icon_html($card['icon'], size: \Filament\Support\Enums\IconSize::Medium) }}
                        </span>
                        <span @class([
                            'agricart-pc-overview__status-badge',
                            'agricart-pc-overview__status-badge--configured' => $card['status'] === 'configured',
                            'agricart-pc-overview__status-badge--defaults' => $card['status'] === 'defaults',
                            'agricart-pc-overview__status-badge--missing' => $card['status'] === 'missing',
                        ])>
                            @switch ($card['status'])
                                @case('configured')
                                    Ready
                                    @break
                                @case('defaults')
                                    Defaults
                                    @break
                                @default
                                    Missing
                            @endswitch
                        </span>
                    </div>
                    <h3 class="agricart-pc-overview__card-title">{{ $card['title'] }}</h3>
                    <dl class="agricart-pc-overview__detail-list">
                        @foreach ($card['rows'] as $row)
                            <div class="agricart-pc-overview__detail-row">
                                <dt>{{ $row['label'] }}</dt>
                                <dd>{{ $row['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <div class="agricart-pc-overview__card-footer">
                        <span class="agricart-pc-overview__card-action">
                            Open settings
                            {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedArrowRight, size: \Filament\Support\Enums\IconSize::Small) }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <section class="agricart-pc-overview__section" aria-labelledby="settings-overview-links-heading">
        <header class="agricart-pc-overview__section-header">
            <h2 id="settings-overview-links-heading" class="agricart-pc-overview__section-title">Quick Links</h2>
            <p class="agricart-pc-overview__section-subtitle">Jump directly to any Settings workspace.</p>
        </header>

        <div class="agricart-pc-overview__grid agricart-pc-overview__grid--links">
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}" class="agricart-pc-overview__card agricart-pc-overview__card--link">
                    <div class="agricart-pc-overview__card-top">
                        <span class="agricart-pc-overview__card-icon">
                            {{ \Filament\Support\generate_icon_html($link['icon'], size: \Filament\Support\Enums\IconSize::Medium) }}
                        </span>
                    </div>
                    <h3 class="agricart-pc-overview__card-title">{{ $link['label'] }}</h3>
                    <p class="agricart-pc-overview__card-hint">{{ $link['description'] }}</p>
                    <div class="agricart-pc-overview__card-footer">
                        <span class="agricart-pc-overview__card-action">
                            Open
                            {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedArrowRight, size: \Filament\Support\Enums\IconSize::Small) }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
</div>
