@extends('layouts.admin')
@section('title', 'Post Categories')
@section('content')
<h1>Post categories</h1><p>Categories organize public news and gallery filters.</p>
<form method="POST" action="{{ route('admin.categories.store') }}" class="panel filter-form">@csrf<label class="field">Name *<input name="name" value="{{ old('name') }}" maxlength="100" required></label><label class="field">Slug<input name="slug" value="{{ old('slug') }}" maxlength="120" placeholder="Generated from name"></label><button class="button" type="submit">Add category</button></form>
@foreach($categories as $category)<div class="panel"><form method="POST" action="{{ route('admin.categories.update', $category) }}" class="filter-form">@csrf @method('PUT')<label class="field">Name<input name="name" value="{{ $category->name }}" required maxlength="100"></label><label class="field">Slug<input name="slug" value="{{ $category->slug }}" required maxlength="120"></label><span>{{ $category->posts_count }} {{ Str::plural('post', $category->posts_count) }}</span><button class="button" type="submit">Save</button></form>
@if($category->posts_count === 0)<form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="Delete this category?">@csrf @method('DELETE')<button class="button button-secondary" type="submit">Delete empty category</button></form>@endif</div>@endforeach
@endsection
