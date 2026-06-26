<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('agricart.brand.name', 'Agricart.pk'))</title>
    <link rel="stylesheet" href="{{ asset('css/catalog.css') }}?v={{ @filemtime(public_path('css/catalog.css')) ?: 1 }}">
    <style>
        :root {
            --store-category-columns: {{ (int) ($storefront['homepage_categories_per_row'] ?? 5) }};
        }
    </style>
</head>
<body class="store-page">
    @include('store.partials.top-bar')
    @include('store.partials.ticker')
    @include('store.partials.header')

    <main class="store-main">
        @yield('breadcrumbs')
        @yield('content')
    </main>

    @include('store.partials.contact-form')
    @include('store.partials.footer')

    @stack('scripts')
</body>
</html>
