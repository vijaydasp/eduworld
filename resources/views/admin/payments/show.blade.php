@extends('layouts.admin')
@section('title', $payment->transaction_reference)
@section('content')
@php($request = $payment->paymentRequest)
@php($linkedApplication = $payment->application ?? $request?->application ?? $payment->enteredApplication)
@php($paymentType = $payment->application ? 'Application-linked payment' : ($linkedApplication ? 'Application-linked payment (entered reference)' : ($request?->isStandalone() ? 'Standalone request payment' : 'Legacy / unlinked payment')))
<p><a href="{{ route('admin.payments.index') }}">← All payments</a></p><h1>{{ $payment->transaction_reference }}</h1>
<div class="grid grid-2"><section class="panel"><h2>Transaction details</h2><dl>
    <dt>Customer</dt><dd>{{ $payment->customer_name }}</dd><dt>Contact</dt><dd>{{ $payment->phone }}<br>{{ $payment->email ?: 'No email supplied' }}</dd>
    <dt>Payment type</dt><dd>{{ $paymentType }}</dd>
    <dt>Service</dt><dd>{{ $payment->serviceLabel() }}</dd>
    <dt>Expected amount</dt><dd>BHD {{ $payment->amount }}</dd><dt>Received amount</dt><dd>BHD {{ $payment->amount_received }}</dd>
    <dt>Status</dt><dd><span class="badge">{{ str($payment->status)->replace('_', ' ')->title() }}</span></dd>
    <dt>Channel</dt><dd>{{ $payment->channel === 'offline' ? 'Offline receipt recorded by an administrator' : 'Online payment request' }}</dd>
    <dt>Provider</dt><dd>{{ $payment->gateway }}</dd><dt>Provider checkout / offline reference</dt><dd>{{ $payment->gateway_reference ?: ($payment->offline_reference ?: 'Not available') }}</dd>
    @if ($payment->channel === 'gateway')
        @if ($payment->gateway === 'afs')
            <dt>AFS transaction ID</dt><dd>{{ data_get($payment->gateway_response, 'provider_reference') ?: 'Not available' }}</dd>
        @endif
        <dt>Provider payment type</dt><dd>{{ $payment->provider_payment_type ?: 'Not available' }}</dd>
        <dt>Provider payment brand</dt><dd>{{ $payment->provider_payment_brand ?: 'Not available' }}</dd>
        <dt>Provider result code</dt><dd>{{ $payment->provider_result_code ?: 'Not available' }}</dd>
        <dt>Provider transaction time</dt><dd>{{ $payment->provider_transaction_at?->format('d M Y H:i:s') ?? 'Not available' }}</dd>
    @endif
    <dt>Reference entered by customer</dt><dd>{{ $payment->entered_reference ?: 'Not supplied' }}</dd>
    @if ($payment->recordedBy)<dt>Recorded by</dt><dd>{{ $payment->recordedBy->name }}</dd>@endif
    @if ($payment->paid_at)<dt>Fully paid at</dt><dd>{{ $payment->paid_at->format('d M Y H:i') }}</dd>@endif
    @if ($payment->refunded_at)<dt>Refund recorded at</dt><dd>{{ $payment->refunded_at->format('d M Y H:i') }}</dd>@endif
    <dt>Remarks</dt><dd>{{ $payment->remarks ?: 'None' }}</dd>
</dl></section>
<section class="panel"><h2>Application / payment request</h2>
    @if ($payment->application)
        <p>Linked to application <a href="{{ route('admin.applications.show', $payment->application) }}">{{ $payment->application->reference_number }}</a>.</p>
    @elseif ($linkedApplication)
        <p>Linked to application <a href="{{ route('admin.applications.show', $linkedApplication) }}">{{ $linkedApplication->reference_number }}</a> <small>(matched from the reference the customer entered — customer-supplied, not a verified binding)</small>.</p>
    @else
        <p>No application is linked to this payment.</p>
    @endif
    @if ($request)
        <h3>{{ $request->typeLabel() }}</h3>
        <dl class="detail-list">
            <dt>Request ID</dt><dd><small>{{ $request->public_id }}</small></dd>
            <dt>Description</dt><dd>{{ $request->description }}</dd>
            <dt>Request amount</dt><dd>BHD {{ number_format((float) $request->amount, 3) }}</dd>
            <dt>Request status</dt><dd><span class="badge">{{ $request->statusLabel() }}</span></dd>
            <dt>Expires</dt><dd>{{ $request->expires_at->format('d M Y, H:i') }}</dd>
            @if ($request->isStandalone())
                <dt>Customer</dt><dd>{{ $request->customer_name }}<br>{{ $request->customer_email }}@if ($request->customer_phone)<br>{{ $request->customer_phone }}@endif</dd>
                <dt>Request page</dt><dd><a href="{{ route('admin.payments.requests.show', $request) }}">View standalone request</a></dd>
            @endif
        </dl>
    @elseif (! $linkedApplication)
        <p>This legacy or unlinked payment has no associated payment request. Existing transaction history is preserved.</p>
    @endif
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
