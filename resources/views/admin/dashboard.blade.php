@extends('layouts.admin')
@section('title','Overview')
@section('content')
<div class="page-heading"><div><span class="eyebrow">EDU WORLD / OVERVIEW</span><h1>A clear view of your day.</h1><p>Applications, conversations and opportunities, in one place.</p></div><span class="muted">{{ now()->format('d F Y') }}</span></div>
<div class="metric-grid">@foreach($metrics as $label=>$value)<div class="metric-card"><span>{{ $label }}</span><strong>{{ $value }}</strong></div>@endforeach</div>
@can('manage-applications')
<div class="panel"><div class="section-heading"><h2>Recent applications</h2><a href="{{ route('admin.applications.index') }}">View all →</a></div><div class="table-wrap"><table><thead><tr><th>Reference</th><th>Applicant</th><th>Service</th><th>Status</th><th>Received</th></tr></thead><tbody>@forelse($recent as $application)<tr><td><a href="{{ route('admin.applications.show',$application) }}">{{ $application->reference_number }}</a></td><td>{{ $application->full_name }}</td><td>{{ config('applications.services.'.$application->service_type.'.label') }}</td><td><span class="badge {{ $application->status }}">{{ ucwords(str_replace('_',' ',$application->status)) }}</span></td><td>{{ $application->created_at->format('d M Y') }}</td></tr>@empty<tr><td colspan="5" class="empty">New applications will appear here.</td></tr>@endforelse</tbody></table></div></div>
@endcan
@can('manage-content')<div class="panel"><h2>Keep your website up to date.</h2><p>Publish news, manage your gallery and review your legal pages.</p><a class="button" href="{{ route('admin.posts.create') }}">Create a post →</a></div>@endcan
@endsection
