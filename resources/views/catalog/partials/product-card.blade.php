<article class="catalog-product-card">
    <div class="catalog-product-card__media">
        @if (filled($product['image_url']))
            <img
                src="{{ $product['image_url'] }}"
                alt="{{ $product['name_en'] }}"
                loading="lazy"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
            >
            <span class="catalog-product-card__placeholder" style="display:none;">{{ strtoupper(substr($product['name_en'], 0, 1)) }}</span>
        @else
            <span class="catalog-product-card__placeholder">{{ strtoupper(substr($product['name_en'], 0, 1)) }}</span>
        @endif
    </div>

    <div class="catalog-product-card__body">
        <span class="catalog-product-card__number">{{ $product['product_number'] }}</span>
        <h2 class="catalog-product-card__title">{{ $product['name_en'] }}</h2>

        @if (filled($product['name_ur']))
            <p class="catalog-product-card__title-ur">{{ $product['name_ur'] }}</p>
        @endif

        <div class="catalog-product-card__stats">
            @if (filled($product['brand']))
                <span class="catalog-stat">{{ $product['brand'] }}</span>
            @endif

            @if (filled($product['packing']))
                <span class="catalog-stat">{{ $product['packing'] }}</span>
            @endif
        </div>
    </div>
</article>
