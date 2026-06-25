<template x-if="hasQuery() && results().length === 0">
    <p class="agricart-global-search__empty">No navigation results found.</p>
</template>

<template x-if="hasQuery() && results().length > 0">
    <ul class="agricart-global-search__results-list">
        <template x-for="(result, index) in results()" :key="result.id">
            <li>
                <a
                    :href="result.url"
                    class="agricart-global-search__result"
                    x-bind:class="{ 'agricart-global-search__result--active': activeIndex === index }"
                    x-on:mouseenter="activeIndex = index"
                    x-on:click="closeAll()"
                    role="option"
                    x-bind:aria-selected="(activeIndex === index).toString()"
                >
                    <span class="agricart-global-search__result-label" x-text="result.label"></span>
                    <span class="agricart-global-search__result-breadcrumb" x-text="result.breadcrumb"></span>
                </a>
            </li>
        </template>
    </ul>
</template>

<template x-if="! hasQuery()">
    <p class="agricart-global-search__hint">Type to search modules, pages, reports, settings, approvals, and documentation.</p>
</template>
