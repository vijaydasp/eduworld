@extends('layouts.admin')
@section('title', 'News & Gallery')
@section('content')
<div class="section-heading"><div><p class="eyebrow">CONTENT MANAGEMENT</p><h1>News & Gallery</h1></div><a class="button" href="{{ route('admin.posts.create') }}">Create a post</a></div>
<p>Publication times use {{ config('app.timezone') }}. Current time: <strong>{{ now()->setTimezone(config('app.timezone'))->format('d M Y, H:i') }}</strong>. Refresh this page after a scheduled time to see the updated status.</p>
<form method="GET" class="panel filter-form">
    <label class="field">Search<input name="search" value="{{ request('search') }}" placeholder="Post title"></label>
    <label class="field">Category<select name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
    <label class="field">Status<select name="status"><option value="">All statuses</option>@foreach(['draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label>
    <label class="field">Featured<select name="featured"><option value="">All posts</option><option value="1" @selected(request('featured') === '1')>Featured</option><option value="0" @selected(request('featured') === '0')>Not featured</option></select></label>
    <button class="button" type="submit">Filter</button><a href="{{ route('admin.posts.index') }}">Reset</a>
</form>
<div class="panel table-wrap"><table><thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Publish date ({{ config('app.timezone') }})</th><th>Actions</th></tr></thead><tbody>
@forelse($posts as $post)<tr><td><strong>{{ $post->title }}</strong>@if($post->is_featured) <span class="badge">Featured</span>@endif</td><td>{{ $post->category?->name ?? 'Uncategorized' }}</td><td><span class="badge">{{ $post->publication_status }}</span></td><td>{{ $post->published_at?->copy()->setTimezone(config('app.timezone'))->format('d M Y H:i') ?? '—' }}</td><td><a href="{{ route('admin.posts.edit', $post) }}">Edit</a> @if($post->isPublic())· <a href="{{ route('news.show', $post->slug) }}" target="_blank" rel="noopener">View</a>@endif</td></tr>
@empty<tr><td colspan="5">No posts match these filters.</td></tr>@endforelse
</tbody></table></div>{{ $posts->links() }}
@endsection
