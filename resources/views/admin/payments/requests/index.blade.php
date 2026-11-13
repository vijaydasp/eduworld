@extends('layouts.admin')
@section('title', 'Standalone payment requests')
@section('content')
<p><a href="{{ route('admin.payments.index') }}">← All payments</a></p>
<h1>Standalone payment requests</h1>
<p>Secure payment links created by the team without an application. Amounts and customer details are fixed after creation.</p>
<p><a class="button" href="{{ route('admin.payments.requests.create') }}">Create payment request</a></p>

<form class="panel" method="GET" action="{{ route('admin.payments.requests.index') }}"><div class="grid grid-3">
    <div class="field"><label for="q">Search requests</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Customer, email, phone, description or request ID"></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>@foreach (\App\Models\PaymentRequest::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
</div><button class="button" type="submit">Filter requests</button> <a href="{{ route('admin.payments.requests.index') }}">Clear filters</a></form>

<div class="table-wrap"><table>
    <thead><tr><th>Request</th><th>Customer</th><th>Amount</th><th>Description</th><th>Status</th><th>Created</th><th>Sent</th><th>Expires</th><th>Paid</th><th>Transaction</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse ($requests as $paymentRequest)
        @php($requestPayment = $paymentRequest->payment)
        <tr>
            <td><small title="{{ $paymentRequest->public_id }}">{{ \Illuminate\Support\Str::limit($paymentRequest->public_id, 18) }}</small></td>
            <td>{{ $paymentRequest->customer_name }}<br><small>{{ $paymentRequest->customer_email }}</small>@if ($paymentRequest->customer_phone)<br><small>{{ $paymentRequest->customer_phone }}</small>@endif</td>
            <td>BHD {{ number_format((float) $paymentRequest->amount, 3) }}<br><small>{{ $paymentRequest->currency }}</small></td>
            <td>{{ $paymentRequest->description }}</td>
            <td><span class="badge">{{ $paymentRequest->statusLabel() }}</span></td>
            <td>{{ $paymentRequest->created_at->format('d M Y, H:i') }}</td>
            <td>{{ $paymentRequest->sent_at?->format('d M Y, H:i') ?? 'Not sent' }}</td>
            <td>{{ $paymentRequest->expires_at->format('d M Y, H:i') }}</td>
            <td>{{ $paymentRequest->paid_at?->format('d M Y, H:i') ?? '—' }}</td>
            <td>@if ($requestPayment)<a href="{{ route('admin.payments.show', $requestPayment) }}">{{ $requestPayment->transaction_reference }}</a>@else — @endif</td>
            <td>
                <a href="{{ route('admin.payments.requests.show', $paymentRequest) }}">View</a>
                @if ($paymentRequest->status === 'sent')
                    <form method="post" action="{{ route('admin.payments.requests.resend', $paymentRequest) }}">@csrf<button class="button button-secondary" type="submit">Resend</button></form>
                    <form method="post" action="{{ route('admin.payments.requests.cancel', $paymentRequest) }}">@csrf<button class="button button-secondary" type="submit">Cancel</button></form>
                @endif
                @if ($requestPayment)
                    <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('pay.receipt', now()->addMinutes(30), ['payment' => $requestPayment->id]) }}">Receipt</a>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="11">No standalone payment requests match these filters.</td></tr>
    @endforelse
    </tbody>
</table></div>{{ $requests->links() }}
@endsection
