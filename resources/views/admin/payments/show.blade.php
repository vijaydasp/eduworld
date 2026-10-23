@extends('layouts.admin')
@section('title', $payment->transaction_reference)
@section('content')
<p><a href="{{ route('admin.payments.index') }}">← All payments</a></p><h1>{{ $payment->transaction_reference }}</h1>
<div class="grid grid-2"><section class="panel"><h2>Transaction details</h2><dl>
    <dt>Customer</dt><dd>{{ $payment->customer_name }}</dd><dt>Contact</dt><dd>{{ $payment->phone }}<br>{{ $payment->email ?: 'No email supplied' }}</dd>
    <dt>Service</dt><dd>{{ config('applications.services.'.$payment->service.'.label', $payment->service) }}</dd>
    <dt>Expected amount</dt><dd>BHD {{ $payment->amount }}</dd><dt>Received amount</dt><dd>BHD {{ $payment->amount_received }}</dd>
    <dt>Status</dt><dd><span class="badge">{{ str($payment->status)->replace('_', ' ')->title() }}</span></dd>
    <dt>Channel</dt><dd>{{ $payment->channel === 'offline' ? 'Offline receipt recorded by an administrator' : 'Online payment request' }}</dd>
    <dt>Provider</dt><dd>{{ $payment->gateway }}</dd><dt>Provider / offline reference</dt><dd>{{ $payment->gateway_reference ?: ($payment->offline_reference ?: 'Not available') }}</dd>
    <dt>Reference entered by customer</dt><dd>{{ $payment->entered_reference ?: 'Not supplied' }}</dd>
    @if ($payment->recordedBy)<dt>Recorded by</dt><dd>{{ $payment->recordedBy->name }}</dd>@endif
    @if ($payment->paid_at)<dt>Fully paid at</dt><dd>{{ $payment->paid_at->format('d M Y H:i') }}</dd>@endif
    @if ($payment->refunded_at)<dt>Refund recorded at</dt><dd>{{ $payment->refunded_at->format('d M Y H:i') }}</dd>@endif
    <dt>Remarks</dt><dd>{{ $payment->remarks ?: 'None' }}</dd>
</dl></section>
<section class="panel"><h2>Application link</h2>
    @if ($payment->application)<p>Linked to <a href="{{ route('admin.applications.show', $payment->application) }}">{{ $payment->application->reference_number }}</a>.</p>@else<p>This payment has not been linked to an application.</p>@endif
    <p>Check the payer and service details before linking. The customer-supplied reference is not verified automatically.</p>
    <form method="POST" action="{{ route('admin.payments.link', $payment) }}">@csrf @method('PATCH')
        <div class="field"><label for="application_reference">Application reference</label><input id="application_reference" name="application_reference" value="{{ old('application_reference', $payment->application?->reference_number) }}" placeholder="EW-2026-00001" required></div>
        <button class="button" type="submit">Link application</button>
    </form>
    @if ($payment->channel === 'gateway')<p>Online settlement status is updated only through verified provider notifications.</p>@endif
    @can('manage-settings')
        @if ($payment->channel === 'offline' && in_array($payment->status, ['paid', 'partially_paid'], true))
            <hr><h3>Record a completed offline refund</h3><p>This records a refund already made outside the website. It does not transfer funds.</p>
            <form method="POST" action="{{ route('admin.payments.offline.refund', $payment) }}">@csrf
                <div class="field"><label for="reason">Refund reference and reason</label><textarea id="reason" name="reason" required minlength="10" maxlength="1000">{{ old('reason') }}</textarea></div>
                <div class="field"><label><input type="checkbox" name="confirmed" value="1" required> I confirm all received funds have been refunded offline.</label></div>
                <button class="button button-secondary" type="submit">Record full refund</button>
            </form>
        @endif
    @endcan
</section></div>
<section class="panel"><h2>Verified provider events</h2><div class="table-wrap"><table><thead><tr><th>Event</th><th>Status</th><th>Processing result</th><th>Received</th></tr></thead><tbody>
@forelse ($events as $event)<tr><td>{{ $event->event_id }}</td><td>{{ str($event->status)->replace('_', ' ')->title() }}</td><td>{{ str($event->result)->replace('_', ' ')->title() }}</td><td>{{ $event->created_at->format('d M Y H:i:s') }}</td></tr>@empty<tr><td colspan="4">No verified provider events have been received.</td></tr>@endforelse
</tbody></table></div>{{ $events->links() }}</section>
@endsection
