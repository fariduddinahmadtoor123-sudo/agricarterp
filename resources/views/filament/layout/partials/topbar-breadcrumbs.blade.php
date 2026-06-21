@php
    $breadcrumbs = app(\App\Support\Navigation\BreadcrumbBuilder::class)->build();
@endphp

@if (filled($breadcrumbs))
    <nav class="agricart-topbar-breadcrumbs" aria-label="Breadcrumb">
        <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />
    </nav>
@endif
