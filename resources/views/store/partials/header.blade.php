<header class="store-header">
    <div class="store-header__search-row">
        <form class="store-search" action="{{ route('catalog.index') }}" method="get" role="search">
            <input
                type="search"
                name="q"
                class="store-search__input"
                placeholder="Search products, categories, or brands..."
                value="{{ request('q') }}"
            />
            <button type="submit" class="store-search__button">Search</button>
        </form>
        <div class="store-header__social">
            @foreach ($storefront['social_links'] ?? [] as $social)
                <a href="{{ $social['url'] }}" class="store-social-link store-social-link--{{ $social['platform'] }}" target="_blank" rel="noopener noreferrer" title="{{ $social['label'] }}">
                    {{ $social['label'] }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="store-header__nav-row">
        <a href="{{ route('catalog.index') }}" class="store-brand">
            @if (filled($storefront['footer_logo_url'] ?? null))
                <img src="{{ $storefront['footer_logo_url'] }}" alt="{{ config('agricart.brand.name', 'Agricart.pk') }}" class="store-brand__logo" />
            @else
                <span class="store-brand__mark">A</span>
            @endif
            <span class="store-brand__name">{{ config('agricart.brand.name', 'Agricart.pk') }}</span>
        </a>

        <nav class="store-nav" aria-label="Main navigation">
            @foreach ($storefront['header_navigation'] ?? [] as $link)
                <a href="{{ $link['url'] }}" class="store-nav__link">{{ $link['label'] }}</a>
            @endforeach
        </nav>

        <div class="store-header__actions">
            <span class="store-header__catalog-note">Catalog</span>
            <button type="button" class="store-header__cart" disabled title="Cart coming in a later phase">
                Cart <span class="store-header__badge">0</span>
            </button>
            <button type="button" class="store-header__account" disabled title="Account coming in a later phase">Account</button>
        </div>
    </div>
</header>
