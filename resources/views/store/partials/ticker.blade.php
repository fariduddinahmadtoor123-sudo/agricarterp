@if (filled($storefront['ticker_en'] ?? null) || filled($storefront['ticker_ur'] ?? null))
    <div class="store-ticker" aria-label="Announcement">
        <div class="store-ticker__track">
            <span>
                {{ $storefront['ticker_ur'] ?? '' }}
                @if (filled($storefront['ticker_en'] ?? null) && filled($storefront['ticker_ur'] ?? null))
                    ——
                @endif
                {{ $storefront['ticker_en'] ?? '' }}
            </span>
            <span aria-hidden="true">
                {{ $storefront['ticker_ur'] ?? '' }}
                @if (filled($storefront['ticker_en'] ?? null) && filled($storefront['ticker_ur'] ?? null))
                    ——
                @endif
                {{ $storefront['ticker_en'] ?? '' }}
            </span>
        </div>
    </div>
@endif
