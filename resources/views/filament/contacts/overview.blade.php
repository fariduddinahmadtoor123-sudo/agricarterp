@php
    $presenter = app(\App\Support\Contacts\ContactOverviewPresenter::class);
    $stats = $presenter->stats();
    $links = $presenter->quickLinks();
@endphp

<div class="agricart-pc-overview">
    <section class="agricart-pc-overview__section" aria-labelledby="contacts-overview-stats-heading">
        <header class="agricart-pc-overview__section-header">
            <h2 id="contacts-overview-stats-heading" class="agricart-pc-overview__section-title">Contact Statistics</h2>
            <p class="agricart-pc-overview__section-subtitle">Live counts across suppliers, customers, and contact records.</p>
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
                        <span class="agricart-pc-overview__card-value">{{ number_format((int) $stat['value']) }}</span>
                    </div>
                    <h3 class="agricart-pc-overview__card-title">{{ $stat['label'] }}</h3>
                    <p class="agricart-pc-overview__card-hint">{{ $stat['hint'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="agricart-pc-overview__section" aria-labelledby="contacts-overview-links-heading">
        <header class="agricart-pc-overview__section-header">
            <h2 id="contacts-overview-links-heading" class="agricart-pc-overview__section-title">Quick Links</h2>
            <p class="agricart-pc-overview__section-subtitle">Jump directly to any Contacts workspace.</p>
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
