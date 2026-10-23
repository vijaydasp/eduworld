@extends('layouts.admin')
@section('title', 'Applications')
@section('content')
<div class="page-intro"><p class="eyebrow">APPLICANT MANAGEMENT</p><h1>Applications</h1><p>Review submissions, track progress and manage applicant documents.</p></div>
<form method="get" class="panel grid form-grid" action="{{ route('admin.applications.index') }}">
    <div class="field"><label for="q">Search name, email, phone or reference</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}"></div>
    <div class="field"><label for="reference">Exact application reference</label><input id="reference" name="reference" value="{{ $filters['reference'] ?? '' }}" placeholder="EW-2026-00001"></div>
    <div class="field"><label for="service">Service</label><select id="service" name="service"><option value="">All services</option>@foreach(config('applications.services') as $key=>$service)<option value="{{ $key }}" @selected(($filters['service'] ?? '') === $key)>{{ $service['label'] }}</option>@endforeach</select></div>
    <div class="field"><label for="status">Application status</label><select id="status" name="status"><option value="">All statuses</option>@foreach(config('applications.statuses') as $key=>$label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
    <div class="field"><label for="payment_status">Payment status</label><select id="payment_status" name="payment_status"><option value="">All payments</option>@foreach(config('applications.payment_statuses') as $key=>$label)<option value="{{ $key }}" @selected(($filters['payment_status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
    <div class="field"><label for="country">Current country</label><input id="country" name="country" value="{{ $filters['country'] ?? '' }}"></div>
    <div class="field"><label for="nationality">Nationality</label><input id="nationality" name="nationality" value="{{ $filters['nationality'] ?? '' }}"></div>
    <div class="field"><label for="date_from">Submitted from</label><input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
    <div class="field"><label for="date_to">Submitted to</label><input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
    <div class="button-row"><button class="button" type="submit">Apply filters</button><a class="button button-secondary" href="{{ route('admin.applications.index') }}">Reset</a></div>
</form>
<div class="panel table-wrap"><table><thead><tr><th scope="col">Reference / received</th><th scope="col">Applicant</th><th scope="col">Service</th><th scope="col">Status</th><th scope="col">Payment</th><th scope="col">Action</th></tr></thead><tbody>
@forelse($applications as $application)
<tr><td><strong>{{ $application->reference_number }}</strong><br><small>{{ $application->created_at->format('d M Y, H:i') }}</small></td><td>{{ $application->full_name }}<br><small>{{ $application->nationality }} · {{ $application->current_country }}</small></td><td>{{ $application->serviceLabel() }}</td><td><span class="badge status-{{ $application->status }}">{{ config('applications.statuses.'.$application->status) }}</span></td><td><span class="badge status-{{ $application->payment_status }}">{{ config('applications.payment_statuses.'.$application->payment_status) }}</span></td><td><a href="{{ route('admin.applications.show', $application) }}">Review <span class="sr-only">{{ $application->reference_number }}</span>→</a></td></tr>
@empty<tr><td colspan="6">No applications match these filters.</td></tr>@endforelse
</tbody></table></div>{{ $applications->links() }}
@endsection
