@extends('layouts.admin')
@section('title', 'Edit '.$page->title)
@section('content')
<p><a href="{{ route('admin.pages.index') }}">← Legal pages</a></p><h1>Edit {{ $page->title }}</h1>
<form method="POST" action="{{ route('admin.pages.update', $page) }}" class="panel form-stack">@csrf @method('PUT')
<label class="field">Title *<input name="title" value="{{ old('title', $page->title) }}" required maxlength="255"></label>
<label class="field">Policy text *<textarea name="body" rows="22" required maxlength="100000">{{ old('body', $page->body) }}</textarea><small>Plain text. Use blank lines between paragraphs. HTML is displayed as text. Use [[company_name]], [[email]], [[phone]] or [[address]] to keep contact details in sync with website settings.</small></label>
<label class="field">SEO title<input name="seo_title" value="{{ old('seo_title', $page->seo_title) }}" maxlength="70"></label>
<label class="field">Meta description<textarea name="meta_description" rows="2" maxlength="170">{{ old('meta_description', $page->meta_description) }}</textarea></label>
<label class="check-field"><input type="checkbox" name="review_required" value="1" @checked(old('review_required', $page->review_required))> Policy still requires business review</label>
<button class="button" type="submit">Save page</button></form>
@endsection
