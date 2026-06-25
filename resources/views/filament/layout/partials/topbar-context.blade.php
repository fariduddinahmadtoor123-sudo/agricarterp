@php
    $context = app(\App\Support\Navigation\TopbarContextBuilder::class);
    $pageTitle = $context->pageTitle();
    $moduleLabel = $context->moduleLabel();
@endphp

<div class="agricart-topbar-context" aria-live="polite">
    @if ($moduleLabel)
        <span class="agricart-topbar-context__module">{{ $moduleLabel }}</span>
    @endif
    <span class="agricart-topbar-context__page">{{ $pageTitle }}</span>
</div>
