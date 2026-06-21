@php
    $breadcrumbs = app(\App\Support\Navigation\BreadcrumbBuilder::class)->build();
@endphp

<div class="agricart-topbar-wayfinding">
    @if (filled($breadcrumbs))
        <nav class="agricart-topbar-breadcrumbs" aria-label="Breadcrumb">
            <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />
        </nav>
    @endif

    @include('filament.layout.partials.topbar-context')
</div>
