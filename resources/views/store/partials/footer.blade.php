<footer class="store-footer">
    <div class="store-footer__inner">
        <div class="store-footer__columns">
            <div class="store-footer__column store-footer__column--brand">
                <div class="store-footer__heading-offset" aria-hidden="true"></div>
                <div class="store-footer__media store-footer__logo-box">
                    @if (filled($storefront['footer_logo_url'] ?? null))
                        <img
                            src="{{ $storefront['footer_logo_url'] }}"
                            alt="{{ config('agricart.brand.name', 'Agricart.pk') }}"
                            class="store-footer__logo"
                        />
                    @else
                        <div class="store-footer__logo-fallback" aria-hidden="true">A</div>
                    @endif
                </div>
            </div>

            <div class="store-footer__column store-footer__column--about">
                <h3>About Store</h3>
                @if (filled($storefront['footer_about_en'] ?? null))
                    <p class="store-footer__about-en">{{ $storefront['footer_about_en'] }}</p>
                @endif
                @if (filled($storefront['footer_about_ur'] ?? null))
                    <p class="store-footer__about-ur" dir="rtl">{{ $storefront['footer_about_ur'] }}</p>
                @endif
            </div>

            <div class="store-footer__column">
                <h3>Quick Links</h3>
                @if (count($storefront['footer_quick_links'] ?? []) > 0)
                    <ul>
                        @foreach ($storefront['footer_quick_links'] as $link)
                            <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="store-footer__column">
                <h3>Legal Links</h3>
                @if (count($storefront['footer_legal_links'] ?? []) > 0)
                    <ul>
                        @foreach ($storefront['footer_legal_links'] as $link)
                            <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="store-footer__column store-footer__column--contact">
                <h3>Contact</h3>
                @if (filled($storefront['contact_email'] ?? null))
                    <p><a href="mailto:{{ $storefront['contact_email'] }}">{{ $storefront['contact_email'] }}</a></p>
                @endif
                @if (filled($storefront['contact_phone'] ?? null))
                    <p><a href="tel:{{ preg_replace('/\s+/', '', $storefront['contact_phone']) }}">{{ $storefront['contact_phone'] }}</a></p>
                @endif
            </div>

            @if (filled($storefront['map_embed_src'] ?? null))
                <div class="store-footer__column store-footer__column--location">
                    <h3>Location</h3>
                    <div class="store-footer__media store-footer__map-mini">
                        <iframe
                            src="{{ $storefront['map_embed_src'] }}"
                            loading="lazy"
                            allowfullscreen
                            referrerpolicy="strict-origin-when-cross-origin"
                            title="Store location"
                        ></iframe>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if (filled($storefront['copyright_line'] ?? null))
        <div class="store-footer__bottom">
            <p>{{ $storefront['copyright_line'] }}</p>
        </div>
    @endif
</footer>
