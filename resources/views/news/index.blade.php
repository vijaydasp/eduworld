@extends('layouts.app')
@section('title', 'Latest News, Jobs, Study & Visa Updates | Edu World Bahrain')
@section('meta_description', 'Get the latest Edu World updates on study abroad, international opportunities, visa preparation, events, photos and videos.')
@section('content')
<section class="page-hero"><div class="container"><p class="eyebrow">THE EDU WORLD JOURNAL</p><h1>Ideas, opportunities<br>& your next chapter.</h1><p>Practical guidance, company updates and stories from the world of study, work and travel.</p></div></section>
<section class="section news-list"><div class="container">
    <p class="eyebrow news-filter-label">Browse by topic</p>
    <nav class="filter-row" aria-label="News categories">
        <a class="news-category-link" href="{{ route('news') }}" @if(!request('category') && !request('tag')) aria-current="page" @endif>All updates</a>
        @foreach($categories as $category)
            <a class="news-category-link" href="{{ route('news', ['category' => $category->slug]) }}" @if(request('category') === $category->slug) aria-current="page" @endif>{{ $category->name }}</a>
        @endforeach
    </nav>
    @if(request('tag'))<p>Tag: {{ request('tag') }} <a href="{{ route('news') }}">Clear filter</a></p>@endif
    <div class="grid news-grid news-list-grid">
        @forelse($posts as $post)
        <article class="panel news-card">
            <a href="{{ route('news.show', $post->slug) }}" tabindex="-1" aria-hidden="true">
                @if($post->image_url)<img src="{{ $post->image_url }}" alt="" loading="lazy" width="640" height="400">
                @else<div class="news-art" aria-hidden="true"><span>{{ $post->category?->name ?? 'Edu World' }}</span><span class="news-art-arrow">↗</span></div>@endif
            </a>
            <div class="news-card-body"><p class="eyebrow">{{ $post->category?->name ?? 'News' }} · <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('d M Y') }}</time></p>
            <h2><a href="{{ route('news.show', $post->slug) }}">{{ $post->title }}</a></h2><p>{{ $post->excerpt }}</p><a class="text-link" href="{{ route('news.show', $post->slug) }}" aria-label="Read {{ $post->title }}">Read the story <span aria-hidden="true">↗</span></a></div>
        </article>
        @empty<div class="panel news-empty"><h2>More stories are on the way.</h2><p>There are no published updates in this category yet. Our team can help with your enquiry in the meantime.</p><div class="button-row"><a class="button" href="{{ route('contact') }}">Contact our team</a>@if(request('category') || request('tag'))<a class="text-link" href="{{ route('news') }}">Browse all updates <span aria-hidden="true">↗</span></a>@endif</div></div>@endforelse
    </div>
    {{ $posts->links() }}
</div></section>
@endsection
