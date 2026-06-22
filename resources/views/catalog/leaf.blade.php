@extends('catalog.layout')

@section('title', $category['name_en'])

@section('content')
    <div class="catalog-leaf">
        <div class="catalog-leaf__media">
            @if (filled($category['image_url']))
                <img
                    src="{{ $category['image_url'] }}"
                    alt="{{ $category['name_en'] }}"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
                >
                <span class="catalog-card__placeholder" style="display:none;">{{ strtoupper(substr($category['name_en'], 0, 1)) }}</span>
            @else
                <span class="catalog-card__placeholder">{{ strtoupper(substr($category['name_en'], 0, 1)) }}</span>
            @endif
        </div>

        <div class="catalog-leaf__panel">
            <h1 class="catalog-leaf__title">{{ $category['name_en'] }}</h1>

            @if (filled($category['name_ur']))
                <p class="catalog-leaf__title-ur">{{ $category['name_ur'] }}</p>
            @endif

            <p class="catalog-leaf__path">{{ $category['full_path'] }}</p>

            <div class="catalog-leaf__stats">
                <span class="catalog-stat catalog-stat--leaf">Leaf category</span>
                <span class="catalog-stat">{{ number_format($category['products_count']) }} products</span>
                <span class="catalog-stat">{{ $category['category_number'] }}</span>
                <span class="catalog-stat">{{ $category['visual_mapping_code'] }}</span>
            </div>

            @if (filled($category['short_description_en']))
                <div class="catalog-leaf__section">
                    <h2 class="catalog-leaf__section-title">Short Description</h2>
                    <p class="catalog-leaf__section-body">{{ $category['short_description_en'] }}</p>
                </div>
            @endif

            @if (filled($category['short_description_ur']))
                <div class="catalog-leaf__section">
                    <h2 class="catalog-leaf__section-title">Short Description (Urdu)</h2>
                    <p class="catalog-leaf__section-body" dir="rtl">{{ $category['short_description_ur'] }}</p>
                </div>
            @endif

            @if (filled($category['description_en']))
                <div class="catalog-leaf__section">
                    <h2 class="catalog-leaf__section-title">Description</h2>
                    <p class="catalog-leaf__section-body">{{ $category['description_en'] }}</p>
                </div>
            @endif

            @if (filled($category['hs_code']))
                <div class="catalog-leaf__section">
                    <h2 class="catalog-leaf__section-title">HS Code</h2>
                    <p class="catalog-leaf__section-body">{{ $category['hs_code'] }}</p>
                </div>
            @endif
        </div>
    </div>
@endsection
