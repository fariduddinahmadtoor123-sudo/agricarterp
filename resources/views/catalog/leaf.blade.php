@extends('store.layout')

@section('title', $category['name_en'] ?? 'Category')

@section('breadcrumbs')
    @include('catalog.partials.breadcrumbs')
@endsection

@section('content')
    <div class="catalog-page-heading">
        <h1 class="catalog-page-heading__title">{{ $category['name_en'] }}</h1>
        @if (filled($category['name_ur'] ?? null))
            <p class="catalog-page-heading__subtitle" dir="rtl">{{ $category['name_ur'] }}</p>
        @endif
    </div>

    <div class="catalog-section">
        <div class="catalog-product-grid">
            @forelse ($products as $product)
                @include('catalog.partials.product-card', ['product' => $product])
            @empty
                <div class="catalog-empty">
                    <p>No products in this category yet.</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection
