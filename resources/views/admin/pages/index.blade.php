@extends('layouts.admin')
@section('title', 'Legal Pages')
@section('content')
<h1>Legal pages</h1><p>Review these service policies against your actual business practices before launch. Mark each page reviewed after the wording is approved.</p>
<div class="panel table-wrap"><table><thead><tr><th>Page</th><th>Review status</th><th>Last updated</th><th>Action</th></tr></thead><tbody>@foreach($pages as $page)<tr><td>{{ $page->title }}</td><td><span class="badge">{{ $page->review_required ? 'Review required' : 'Reviewed' }}</span></td><td>{{ $page->updated_at->format('d M Y') }}</td><td><a href="{{ route('admin.pages.edit', $page) }}">Edit</a> · <a href="{{ route($page->slug) }}" target="_blank" rel="noopener">View</a></td></tr>@endforeach</tbody></table></div>
@endsection
