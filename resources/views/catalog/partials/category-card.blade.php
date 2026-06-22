<a href="{{ $category['url'] }}" class="catalog-card">
    <div class="catalog-card__media">
        @if (filled($category['image_url']))
            <img
                src="{{ $category['image_url'] }}"
                alt="{{ $category['name_en'] }}"
                loading="lazy"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
            >
            <span class="catalog-card__placeholder" style="display:none;">{{ strtoupper(substr($category['name_en'], 0, 1)) }}</span>
        @else
            <span class="catalog-card__placeholder">{{ strtoupper(substr($category['name_en'], 0, 1)) }}</span>
        @endif
    </div>

    <div class="catalog-card__body">
        <h2 class="catalog-card__title">{{ $category['name_en'] }}</h2>

        @if (filled($category['name_ur']))
            <p class="catalog-card__title-ur">{{ $category['name_ur'] }}</p>
        @endif

        <div class="catalog-card__stats">
            <span class="catalog-stat">{{ number_format($category['products_count']) }} products</span>

            @if ($category['children_count'] > 0)
                <span class="catalog-stat">{{ number_format($category['children_count']) }} subcategories</span>
            @else
                <span class="catalog-stat catalog-stat--leaf">Leaf category</span>
            @endif
        </div>
    </div>
</a>
