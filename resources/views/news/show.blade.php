@extends('layouts.app')
@section('title', $post->seo_title ?: $post->title.' | Edu World')
@section('meta_description', $post->meta_description ?: $post->excerpt)
@section('og_image', $post->image_url ?? '')
@section('og_type', 'article')
@push('head')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $post->title, 'description' => $post->excerpt, 'datePublished' => $post->published_at->toIso8601String(), 'dateModified' => $post->updated_at->toIso8601String(), 'author' => ['@type' => 'Organization', 'name' => $post->author_name], 'publisher' => ['@type' => 'Organization', 'name' => 'Edu World', 'url' => config('app.url')], 'mainEntityOfPage' => route('news.show', $post->slug)] + ($post->image_url ? ['image' => $post->image_url] : []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')], ['@type' => 'ListItem', 'position' => 2, 'name' => 'News & Gallery', 'item' => route('news')], ['@type' => 'ListItem', 'position' => 3, 'name' => $post->title, 'item' => route('news.show', $post->slug)]]], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
@section('content')
<article class="section"><div class="container article-container">
    <a class="text-link" href="{{ route('news') }}">← Back to news & gallery</a>
    <p class="eyebrow">{{ $post->category?->name ?? 'News' }} · <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('d F Y') }}</time></p>
    <h1>{{ $post->title }}</h1><p class="lead">{{ $post->excerpt }}</p><p>By {{ $post->author_name }}</p>
    @if($post->image_url)<img class="article-image" src="{{ $post->image_url }}" alt="{{ $post->featured_image_alt ?: $post->title }}" width="1200" height="750">@endif
    <div class="prose plain-content">{{ $post->body }}</div>
    @if($post->youtube_embed_url)
        <div class="video-frame"><iframe src="{{ $post->youtube_embed_url }}" title="Video: {{ $post->title }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="fullscreen; picture-in-picture" allowfullscreen></iframe></div>
    @endif
    @if($post->media->isNotEmpty())<section aria-label="Post gallery" class="grid gallery-grid">@foreach($post->media as $media)
        @if($media->type === 'image')<figure><a href="{{ $media->url }}" target="_blank" rel="noopener"><img src="{{ $media->url }}" alt="{{ $media->alt_text ?: $post->title }}" loading="lazy" width="640" height="420"></a></figure>
        @else<video controls preload="metadata" aria-label="{{ $media->alt_text ?: $post->title }}"><source src="{{ $media->url }}" type="video/mp4">Your browser cannot play this video. <a href="{{ $media->url }}">Download the video</a>.</video>@endif
    @endforeach</section>@endif
    @if($post->tags->isNotEmpty())<div class="filter-row" aria-label="Tags">@foreach($post->tags as $tag)<a class="badge" href="{{ route('news', ['tag' => $tag->slug]) }}">{{ $tag->name }}</a>@endforeach</div>@endif
    @if($post->cta_url && $post->cta_label)<div class="panel"><h2>Take the next step.</h2><a class="button" href="{{ $post->cta_url }}">{{ $post->cta_label }} <span aria-hidden="true">↗</span></a></div>@endif
</div></article>
@endsection
