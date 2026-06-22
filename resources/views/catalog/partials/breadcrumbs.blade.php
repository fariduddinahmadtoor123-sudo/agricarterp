@if (! empty($breadcrumbs))
    <nav class="catalog-breadcrumbs" aria-label="Breadcrumb">
        @foreach ($breadcrumbs as $index => $crumb)
            @if ($index > 0)
                <span class="catalog-breadcrumbs__sep" aria-hidden="true">→</span>
            @endif

            <span class="catalog-breadcrumbs__item">
                @if (filled($crumb['url'] ?? null))
                    <a href="{{ $crumb['url'] }}" class="catalog-breadcrumbs__link">{{ $crumb['label'] }}</a>
                @else
                    <span class="catalog-breadcrumbs__current">{{ $crumb['label'] }}</span>
                @endif
            </span>
        @endforeach
    </nav>
@endif
