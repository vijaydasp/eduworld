@extends('layouts.admin')
@section('title', $application->reference_number)
@section('content')
<a href="{{ route('admin.applications.index') }}">← All applications</a>
<div class="page-intro">
    <p class="eyebrow">{{ $application->serviceLabel() }}</p>
    <h1>{{ $application->reference_number }}</h1>
    <p>{{ $application->full_name }} · Received {{ $application->created_at->format('d M Y, H:i') }}</p>
</div>
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-error" role="alert">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="grid form-grid">
    <section class="panel">
        <h2>Applicant information</h2>
        <dl class="detail-list">
            @foreach(['full_name'=>'Full name', 'nationality'=>'Nationality', 'date_of_birth'=>'Date of birth', 'current_country'=>'Current country', 'current_city'=>'Current city', 'phone'=>'Mobile / WhatsApp', 'email'=>'Email', 'preferred_contact_method'=>'Preferred contact method'] as $key=>$label)
                <dt>{{ $label }}</dt>
                <dd>{{ $key === 'date_of_birth' ? ($application->date_of_birth?->format('d M Y') ?? '—') : ($application->{$key} ?: '—') }}</dd>
            @endforeach
            <dt>Accuracy consent</dt><dd>{{ $application->consent_accuracy ? 'Confirmed' : 'Not confirmed' }}</dd>
            <dt>Processing consent</dt><dd>{{ $application->consent_processing ? 'Confirmed' : 'Not confirmed' }}</dd>
            <dt>Consent recorded</dt><dd>{{ $application->consented_at->format('d M Y, H:i') }} · Policy version {{ $application->consent_version }}</dd>
        </dl>
    </section>
    <section class="panel">
        <h2>{{ $application->serviceLabel() }} details</h2>
        <dl class="detail-list">
            @foreach(config('applications.services.'.$application->service_type.'.fields', []) as $key=>$field)
                <dt>{{ $field['label'] }}</dt>
                <dd class="preserve-lines">@php($value = $details?->{$key} ?? ($key === 'remarks' ? $application->remarks : null)){{ is_array($value) ? implode(', ', $value) : ($value ?? '—') }}</dd>
            @endforeach
        </dl>
    </section>
</div>

<section class="panel" id="edit-application">
    <h2>Edit application details</h2>
    <p>Changes are saved against the same application reference and are used for future payment request emails. The application reference cannot be changed.</p>
    <form method="post" action="{{ route('admin.applications.update', $application) }}">
        @csrf @method('PATCH')
        <input type="hidden" name="status" value="{{ old('status', $application->status ?: 'new') }}">
        <div class="grid form-grid">
            <div class="field"><label for="full_name">Full name</label><input id="full_name" name="full_name" value="{{ old('full_name', $application->full_name) }}" maxlength="150" required></div>
            <div class="field"><label for="email">Registered email</label><input id="email" name="email" type="email" value="{{ old('email', $application->email) }}" maxlength="254" required><small>Payment request emails are sent to this address.</small></div>
            <div class="field"><label for="phone">Mobile / WhatsApp</label><input id="phone" name="phone" type="tel" value="{{ old('phone', $application->phone) }}" maxlength="30" required></div>
            <div class="field"><label for="nationality">Nationality</label><input id="nationality" name="nationality" value="{{ old('nationality', $application->nationality) }}" maxlength="100" required></div>
            <div class="field"><label for="date_of_birth">Date of birth</label><input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', $application->date_of_birth?->format('Y-m-d')) }}"></div>
            <div class="field"><label for="current_country">Current country</label><input id="current_country" name="current_country" value="{{ old('current_country', $application->current_country) }}" maxlength="100" required></div>
            <div class="field"><label for="current_city">Current city</label><input id="current_city" name="current_city" value="{{ old('current_city', $application->current_city) }}" maxlength="100"></div>
            <div class="field"><label for="preferred_contact_method">Preferred contact method</label><select id="preferred_contact_method" name="preferred_contact_method"><option value="">Not specified</option>@foreach(['whatsapp'=>'WhatsApp', 'phone'=>'Phone', 'email'=>'Email'] as $value=>$label)<option value="{{ $value }}" @selected(old('preferred_contact_method', $application->preferred_contact_method) === $value)>{{ $label }}</option>@endforeach</select></div>
        </div>
        @if(config('applications.services.'.$application->service_type.'.fields'))
            <h3>Service / course details</h3>
            <div class="grid form-grid">
                @foreach(config('applications.services.'.$application->service_type.'.fields') as $key=>$field)
                    @php($value = old('details.'.$key, $details?->{$key} ?? ($key === 'remarks' ? $application->remarks : null)))
                    <div class="field">
                        <label for="details_{{ $key }}">{{ $field['label'] }}</label>
                        @if($field['type'] === 'select')
                            <select id="details_{{ $key }}" name="details[{{ $key }}]"><option value="">Not specified</option>@foreach($field['options'] as $option)<option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>@endforeach</select>
                        @elseif($field['type'] === 'textarea')
                            <textarea id="details_{{ $key }}" name="details[{{ $key }}]" rows="3" maxlength="5000">{{ $value }}</textarea>
                        @elseif($field['type'] === 'number')
                            <input id="details_{{ $key }}" name="details[{{ $key }}]" type="number" value="{{ $value }}" min="{{ $field['min'] }}" max="{{ $field['max'] }}">
                        @elseif($field['type'] === 'date')
                            <input id="details_{{ $key }}" name="details[{{ $key }}]" type="date" value="{{ $value instanceof \Illuminate\Support\Carbon ? $value->format('Y-m-d') : $value }}">
                        @elseif($field['type'] === 'checkboxes')
                            @php($selected = old('details.'.$key, is_array($value) ? $value : []))
                            <div class="button-row">@foreach($field['options'] as $option)<label><input type="checkbox" name="details[{{ $key }}][]" value="{{ $option }}" @checked(in_array($option, $selected, true))> {{ $option }}</label>@endforeach</div>
                        @else
                            <input id="details_{{ $key }}" name="details[{{ $key }}]" value="{{ $value }}" maxlength="255">
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
        <button class="button" type="submit">Save application details</button>
    </form>
</section>

<section class="panel" id="payment-request">
    <h2>Payment request</h2>
    <p>Finalize the amount agreed with the applicant. The application reference, applicant details and registered email are taken automatically from this application.</p>
    <dl class="detail-list">
        <dt>Application reference</dt><dd>{{ $application->reference_number }}</dd>
        <dt>Student name</dt><dd>{{ $application->full_name }}</dd>
        <dt>Registered email</dt><dd>{{ $application->email }}</dd>
        <dt>Service / course</dt><dd>{{ $application->serviceLabel() }}</dd>
        <dt>Application status</dt><dd>{{ config('applications.statuses.'.$application->status, $application->status) }}</dd>
        <dt>Payment status</dt><dd><span class="badge status-{{ $application->payment_status }}">{{ config('applications.payment_statuses.'.$application->payment_status, $application->payment_status) }}</span></dd>
    </dl>

    @php($activeRequest = $application->paymentRequests->first(fn ($request) => $request->status === 'sent' && $request->expires_at?->isFuture()))
    @if($application->payment_status === 'paid' || $application->payments->contains('status', 'paid'))
        <div class="alert alert-success" role="status">This application already has a successful payment. Further payment requests cannot be created.</div>
    @else
        @if($activeRequest)
            <div class="alert" role="status">An active request for BHD {{ number_format((float) $activeRequest->amount, 3) }} exists and expires {{ $activeRequest->expires_at->format('d M Y, H:i') }}.</div>
        @endif
        <form method="post" action="{{ route('admin.applications.payment-requests.store', $application) }}">
            @csrf
            <div class="grid form-grid">
                <div class="field"><label for="amount">Agreed amount (BHD)</label><input id="amount" name="amount" value="{{ old('amount') }}" inputmode="decimal" placeholder="0.000" pattern="[0-9]+(\.[0-9]{1,3})?" required><small>Up to three decimal places, between 0.001 and {{ config('payments.maximum_amount') }}.</small></div>
                <div class="field"><label for="validity_days">Payment link validity (days)</label><input id="validity_days" name="validity_days" type="number" value="{{ old('validity_days', 7) }}" min="1" max="30" required></div>
            </div>
            <div class="field"><label for="description">Payment description</label><input id="description" name="description" value="{{ old('description') }}" maxlength="255" placeholder="e.g. Study abroad service fee" required></div>
            @if($activeRequest)
                <div class="field"><label><input type="checkbox" name="replace_existing" value="1" @checked(old('replace_existing'))> Cancel the existing unpaid request and create a new request.</label></div>
            @endif
            <div class="field"><label><input type="checkbox" name="confirmed" value="1" @checked(old('confirmed')) required> I confirm that this amount has been discussed and agreed with the applicant.</label></div>
            <button class="button" type="submit">Approve &amp; Send Payment Request</button>
        </form>
    @endif

    <h3>Payment request history</h3>
    <div class="table-wrap"><table>
        <thead><tr><th>Request</th><th>Amount</th><th>Description</th><th>Created</th><th>Email sent</th><th>Expires</th><th>Status</th><th>Transaction</th><th>Gateway payment ID</th><th>Paid</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($application->paymentRequests as $request)
            @php($requestPayment = $request->payment)
            <tr>
                <td><small title="{{ $request->public_id }}">{{ $request->public_id }}</small></td>
                <td>BHD {{ number_format((float) $request->amount, 3) }}</td>
                <td>{{ $request->description }}</td>
                <td>{{ $request->created_at->format('d M Y, H:i') }}</td>
                <td>{{ $request->sent_at?->format('d M Y, H:i') ?? 'Not sent' }}</td>
                <td>{{ $request->expires_at->format('d M Y, H:i') }}</td>
                <td><span class="badge">{{ $request->statusLabel() }}</span></td>
                <td>{{ $requestPayment?->transaction_reference ?? '—' }}</td>
                <td>{{ data_get($requestPayment?->gateway_response, 'provider_reference') ?? $requestPayment?->gateway_reference ?? '—' }}</td>
                <td>{{ $request->paid_at?->format('d M Y, H:i') ?? '—' }}</td>
                <td>
                    @if($request->status === 'sent')
                        <form method="post" action="{{ route('admin.applications.payment-requests.resend', [$application, $request]) }}">@csrf<button class="button button-secondary" type="submit">Resend payment email</button></form>
                        <form method="post" action="{{ route('admin.applications.payment-requests.cancel', [$application, $request]) }}">@csrf<button class="button button-secondary" type="submit">Cancel unpaid request</button></form>
                    @endif
                    @if($requestPayment)
                        @can('manage-payments')<a href="{{ route('admin.payments.show', $requestPayment) }}">View transaction</a>@endcan
                        <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('pay.receipt', now()->addMinutes(30), ['payment' => $requestPayment->id]) }}">View / download receipt</a>
                    @endif
                    @if(! $activeRequest && $request->status !== 'paid' && $application->payment_status !== 'paid')
                        <a href="#payment-request">Create new payment request</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="11">No payment requests have been created for this application.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>

<div class="grid form-grid">
    <form method="post" action="{{ route('admin.applications.update', $application) }}" class="panel">@csrf @method('PATCH')
        <h2>Manage application</h2>
        <div class="field"><label for="status">Application status</label><select name="status" id="status">@foreach(config('applications.statuses') as $key=>$label)<option value="{{ $key }}" @selected(old('status', $application->status) === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="status_note">Add a history note</label><textarea id="status_note" name="status_note" rows="3" maxlength="5000">{{ old('status_note') }}</textarea></div>
        <div class="field"><label for="admin_notes">Internal notes</label><textarea id="admin_notes" name="admin_notes" rows="6" maxlength="20000">{{ old('admin_notes', $application->admin_notes) }}</textarea></div>
        <p>Notes are visible to authorized staff only. Application status is separate from payment status.</p>
        <button class="button" type="submit">Save application</button>
    </form>
    <section class="panel">
        <h2>Status history</h2>
        <ol class="history-list">@foreach($application->statusHistory as $history)<li><strong>{{ config('applications.statuses.'.$history->to_status) }}</strong><br><small>{{ $history->created_at->format('d M Y, H:i') }} · {{ $history->user?->name ?? 'Online application' }}</small>@if($history->from_status)<p>Previous: {{ config('applications.statuses.'.$history->from_status) }}</p>@endif @if($history->notes)<p class="preserve-lines">{{ $history->notes }}</p>@endif</li>@endforeach</ol>
    </section>
</div>

<section class="panel">
    <h2>Protected documents</h2>
    <p>Downloads are restricted to authorized administrators and recorded in the activity log.</p>
    <div class="table-wrap"><table><thead><tr><th>Document</th><th>File name</th><th>Size</th><th>Action</th></tr></thead><tbody>@forelse($application->documents as $document)<tr><td>{{ config('applications.services.'.$application->service_type.'.uploads.'.$document->document_type, $document->document_type) }}</td><td>{{ $document->original_name }}</td><td>{{ number_format($document->size / 1024, 1) }} KB</td><td><a href="{{ route('admin.documents.download', $document) }}">Download</a></td></tr>@empty<tr><td colspan="4">No documents were uploaded.</td></tr>@endforelse</tbody></table></div>
</section>

<section class="panel">
    <h2>Payment records</h2>
    <p>Application payment status: <span class="badge status-{{ $application->payment_status }}">{{ config('applications.payment_statuses.'.$application->payment_status) }}</span></p>
    <p>Payment status is updated through verified transactions or authorized offline payment records.</p>
    <div class="table-wrap"><table><thead><tr><th>Transaction</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>@forelse($application->payments as $payment)<tr><td>{{ $payment->transaction_reference }}</td><td>{{ $payment->currency }} {{ number_format((float) $payment->amount, 3) }}</td><td>{{ config('applications.payment_statuses.'.$payment->status, $payment->status) }}</td><td>@can('manage-payments')<a href="{{ route('admin.payments.show', $payment) }}">View transaction</a>@endcan</td></tr>@empty<tr><td colspan="4">No payments are associated with this application.</td></tr>@endforelse</tbody></table></div>
</section>
@endsection
