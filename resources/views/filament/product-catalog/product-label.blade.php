<article class="agricart-product-print-label" aria-label="Product label {{ $label['product_number'] }}">
    <div class="agricart-product-print-label__thumb">
        @if (filled($label['image_url'] ?? null))
            <img
                src="{{ $label['image_url'] }}"
                alt="{{ $label['name_en'] }}"
                class="agricart-product-print-label__image"
                loading="lazy"
            >
        @else
            <span class="agricart-product-print-label__image-fallback">{{ strtoupper(substr($label['name_en'], 0, 1)) }}</span>
        @endif
    </div>

    <div class="agricart-product-print-label__body">
        <div class="agricart-product-print-label__code">{{ $label['product_number'] }}</div>
        <h3 class="agricart-product-print-label__name">{{ $label['name_en'] }}</h3>

        @if (filled($label['brand'] ?? null))
            <div class="agricart-product-print-label__line"><strong>Brand:</strong> {{ $label['brand'] }}</div>
        @endif

        @if (filled($label['packing'] ?? null))
            <div class="agricart-product-print-label__line"><strong>Pack:</strong> {{ $label['packing'] }}</div>
        @endif

        @if (filled($label['category_name'] ?? null))
            <div class="agricart-product-print-label__line"><strong>Category:</strong> {{ $label['category_name'] }}</div>
        @endif

        @if (filled($label['attribute_line'] ?? null))
            <div class="agricart-product-print-label__line agricart-product-print-label__line--compact"><strong>Attributes:</strong> {{ $label['attribute_line'] }}</div>
        @endif

        @if (filled($label['controls_line'] ?? null))
            <div class="agricart-product-print-label__line agricart-product-print-label__line--compact"><strong>Controls:</strong> {{ $label['controls_line'] }}</div>
        @endif

        @if (filled($label['display_tags'] ?? []))
            <div class="agricart-product-print-label__line agricart-product-print-label__line--compact"><strong>Display:</strong> {{ implode(', ', $label['display_tags']) }}</div>
        @endif
    </div>

    @if (filled($label['qr_url'] ?? null))
        <div class="agricart-product-print-label__qr">
            <img src="{{ $label['qr_url'] }}" alt="QR {{ $label['product_number'] }}" width="72" height="72">
        </div>
    @endif
</article>
