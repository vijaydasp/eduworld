@extends('layouts.admin')
@section('title', $post->exists ? 'Edit Post' : 'Create Post')
@section('content')
<p><a href="{{ route('admin.posts.index') }}">← News & Gallery</a></p><h1>{{ $post->exists ? 'Edit post' : 'Create a post' }}</h1>
@if($post->exists)
<div class="panel" role="status"><strong>{{ $post->publication_status }}</strong><p>{{ $post->publicationMessage() }}</p>@if($post->isPublic())<a href="{{ route('news.show', $post->slug) }}" target="_blank" rel="noopener">View public post →</a>@endif</div>
@endif
<form method="POST" enctype="multipart/form-data" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}" class="panel form-stack">
@csrf @if($post->exists) @method('PUT') @endif
<div class="grid form-grid">
<label class="field">Title *<input name="title" value="{{ old('title', $post->title) }}" maxlength="255" required></label>
<label class="field">URL slug<input name="slug" value="{{ old('slug', $post->slug) }}" maxlength="255"><small>Leave blank to generate from the title. Each slug must be unique.</small></label>
<label class="field">Category *<select name="category_id" required><option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $post->category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
<label class="field">Author *<input name="author_name" value="{{ old('author_name', $post->author_name) }}" maxlength="120" required></label>
</div>
<label class="field">Excerpt<textarea name="excerpt" rows="3" maxlength="600">{{ old('excerpt', $post->excerpt) }}</textarea></label>
<label class="field">Full description *<textarea name="body" rows="15" maxlength="100000" required>{{ old('body', $post->body) }}</textarea><small>Use plain text and blank lines between paragraphs. HTML is displayed as text.</small></label>
<fieldset><legend>Publication</legend>
<p>Current {{ config('app.timezone') }} time: <strong>{{ now()->setTimezone(config('app.timezone'))->format('d M Y, H:i') }}</strong> (when this page opened).</p>
<p>Times below use {{ config('app.timezone') }}, regardless of your computer's timezone. A date alone does not publish a draft.</p>
<div class="grid form-grid">
<label class="check-field"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $post->is_published))> Publish this post</label>
<label class="check-field"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $post->is_featured))> Feature this post</label>
<label class="field">Publication date & time<input type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->copy()->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}"><small>Required for publication. Future dates schedule the post. Time zone: {{ config('app.timezone') }}. Select Publish this post to publish at this time.</small></label>
</div></fieldset>
<fieldset><legend>Images & video</legend>
<div class="grid form-grid"><label class="field">Featured image<input type="file" name="featured_upload" accept="image/jpeg,image/png,image/webp"><small>JPEG, PNG or WebP; up to 5 MB and 5000 × 5000 pixels. Images are optimized for the website.</small></label>
<label class="field">Featured image description<input name="featured_image_alt" value="{{ old('featured_image_alt', $post->featured_image_alt) }}" maxlength="255"></label></div>
@if($post->image_url)<img class="admin-media-thumb" src="{{ $post->image_url }}" alt="Current featured image"><label class="check-field"><input type="checkbox" name="remove_featured" value="1"> Remove current featured image</label>@endif
<div class="grid form-grid"><label class="field">Add gallery photos<input type="file" name="gallery[]" accept="image/jpeg,image/png,image/webp" multiple><small>Up to 12 per save, 5 MB each. Existing images remain until removed below.</small></label>
<label class="field">Description for new gallery photos<input name="gallery_alt" value="{{ old('gallery_alt') }}" maxlength="255"></label>
<label class="field">Add video<input type="file" name="video_upload" accept="video/mp4"><small>MP4 only, up to 30 MB. YouTube links are also supported.</small></label>
<label class="field">YouTube video link<input type="url" name="youtube_url" value="{{ old('youtube_url', $post->youtube_id ? 'https://www.youtube.com/watch?v='.$post->youtube_id : '') }}" placeholder="https://www.youtube.com/watch?v=..." maxlength="2048"></label></div>
</fieldset>
<fieldset><legend>Action button & search appearance</legend><div class="grid form-grid">
<label class="field">Button label<input name="cta_label" value="{{ old('cta_label', $post->cta_label) }}" maxlength="80" placeholder="Apply online"></label>
<label class="field">Button link<input name="cta_url" value="{{ old('cta_url', $post->cta_url) }}" maxlength="2048" placeholder="/apply"><small>Use a local path or HTTPS URL.</small></label>
<label class="field">SEO title<input name="seo_title" value="{{ old('seo_title', $post->seo_title) }}" maxlength="70"></label>
<label class="field">Keywords / tags<input name="tags" value="{{ old('tags', $post->exists ? $post->tags->pluck('name')->join(', ') : '') }}" maxlength="500" placeholder="Study abroad, Admissions"><small>Separate tags with commas. Up to 20 tags.</small></label>
</div><label class="field">Meta description<textarea name="meta_description" rows="2" maxlength="170">{{ old('meta_description', $post->meta_description) }}</textarea></label></fieldset>
<button class="button" type="submit">Save post</button>
</form>
@if($post->exists)
@if($post->media->isNotEmpty())<section class="panel"><h2>Current gallery</h2><div class="grid gallery-grid">@foreach($post->media as $media)<div>
@if($media->type === 'image')<img class="admin-media-thumb" src="{{ $media->url }}" alt="{{ $media->alt_text }}">@else<video controls preload="metadata" class="admin-media-thumb" src="{{ $media->url }}"></video>@endif
<p>{{ $media->original_name }}</p><form method="POST" action="{{ route('admin.posts.media.destroy', [$post, $media]) }}" data-confirm="Remove this media permanently?">@csrf @method('DELETE')<button class="button button-secondary" type="submit">Remove media</button></form></div>@endforeach</div></section>@endif
<details class="panel"><summary>Delete this post</summary><p>This permanently removes the post and its uploaded media.</p><form method="POST" action="{{ route('admin.posts.destroy', $post) }}" data-confirm="Permanently delete this post and its media?">@csrf @method('DELETE')<button class="button button-secondary" type="submit">Delete post</button></form></details>
@endif
@endsection
