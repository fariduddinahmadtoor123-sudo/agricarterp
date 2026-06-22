@extends('catalog.layout')

@section('title', $title)

@section('content')
    <div class="catalog-page-heading">
        <h1 class="catalog-page-heading__title">{{ $title }}</h1>

        @if (filled($subtitle))
            <p class="catalog-page-heading__subtitle">{{ $subtitle }}</p>
        @endif

        @if ($parentCategory !== null)
            <div class="catalog-page-heading__meta">
                <span class="catalog-meta-pill">{{ $parentCategory->category_number }}</span>
                <span class="catalog-meta-pill">{{ $parentCategory->visual_mapping_code }}</span>
            </div>
        @endif
    </div>

    @if (count($categories) > 0)
        <div class="catalog-section">
            <h2 class="catalog-section__title">Categories</h2>
            <div class="catalog-grid">
                @foreach ($categories as $category)
                    @include('catalog.partials.category-card', ['category' => $category])
                @endforeach
            </div>
        </div>
    @elseif (count($products) === 0)
        <div class="catalog-empty">
            <p>No categories found at this level yet.</p>
            <p>Add categories in the admin panel to explore the hierarchy here.</p>
        </div>
    @endif

    @if (count($products) > 0)
        <div class="catalog-section {{ count($categories) > 0 ? 'catalog-section--spaced' : '' }}">
            <h2 class="catalog-section__title">Products</h2>
            <div class="catalog-product-grid">
                @foreach ($products as $product)
                    @include('catalog.partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </div>
    @endif
@endsection
