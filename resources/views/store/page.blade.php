@extends('store.layout')

@section('title', $page->title_en)

@section('content')
    <article class="store-page-content">
        <header class="store-page-content__header">
            <h1>{{ $page->title_en }}</h1>
            @if (filled($page->title_ur))
                <p class="store-page-content__title-ur" dir="rtl">{{ $page->title_ur }}</p>
            @endif
        </header>

        <div class="store-page-content__body store-rich-content">
            {!! $contentHtml !!}
        </div>

        @if (filled(strip_tags($contentHtmlUr)))
            <div class="store-page-content__body store-rich-content store-page-content__body--ur" dir="rtl">
                {!! $contentHtmlUr !!}
            </div>
        @endif
    </article>
@endsection
