@extends('layouts.app')
@section('title', $page->seo_title ?: $page->title.' | Edu World Bahrain')
@section('meta_description', $page->meta_description ?: $page->title.' for Edu World services in Bahrain.')
@section('content')
<section class="page-hero"><div class="container"><p class="eyebrow">INFORMATION & POLICIES</p><h1>{{ $page->title }}</h1></div></section>
<section class="section"><div class="container article-container"><p>Last updated: <time datetime="{{ $page->updated_at->toDateString() }}">{{ $page->updated_at->format('d F Y') }}</time></p><div class="prose plain-content">{{ $page->rendered_body }}</div><div class="panel"><h2>Need clarification?</h2><p>Our team can explain the terms relevant to your application before you proceed.</p><a class="button" href="{{ route('contact') }}">Contact {{ \App\Models\Setting::getValue('company_name', 'Edu World') }}</a></div></div></section>
@endsection
