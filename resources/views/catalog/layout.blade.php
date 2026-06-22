<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Category Catalog') — {{ config('agricart.brand.name', 'Agricart ERP') }}</title>
    <link rel="stylesheet" href="{{ asset('css/catalog.css') }}?v={{ filemtime(public_path('css/catalog.css')) }}">
</head>
<body class="catalog-page">
    <header class="catalog-header">
        <div class="catalog-header__inner">
            <a href="{{ route('catalog.index') }}" class="catalog-brand">
                <span class="catalog-brand__mark">A</span>
                <span class="catalog-brand__text">
                    <span class="catalog-brand__name">{{ config('agricart.brand.name', 'Agricart ERP') }}</span>
                    <span class="catalog-brand__tagline">Category Catalog Prototype</span>
                </span>
            </a>
            <span class="catalog-prototype-badge">Hierarchy Explorer</span>
        </div>
    </header>

    <main class="catalog-main">
        @include('catalog.partials.breadcrumbs')

        @yield('content')
    </main>

    <footer class="catalog-footer">
        Temporary catalog view for testing category hierarchy, images, and navigation.
    </footer>
</body>
</html>
