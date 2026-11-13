@extends('layouts.admin')
@section('title', 'Payments')
@section('content')
<h1>Payments</h1><p>Review verified gateway transactions, pending payment requests and recorded offline receipts.</p>
@if (in_array(config('mail.default'), ['log', 'array'], true))
    <div class="alert error" role="alert">Email delivery is not configured (mailer: {{ config('mail.default') }}). Payment requests are recorded and marked as sent, but customers will not receive them. Set <code>MAIL_MAILER=smtp</code> with valid credentials, then resend.</div>
@endif
<p><a class="button" href="{{ route('admin.payments.requests.create') }}">Create payment request</a> <a class="button button-secondary" href="{{ route('admin.payments.requests.index') }}">Standalone requests</a>@can('manage-settings') <a class="button button-secondary" href="{{ route('admin.payments.offline.create') }}">Record offline receipt</a>@endcan</p>

@if ($pendingRequests->isNotEmpty())
<section class="panel">
    <h2>Payment requests awaiting payment</h2>
    <p>These requests have not started a checkout yet, so they have no payment transaction. Standalone requests appear here as soon as they are emailed.</p>
    <div class="table-wrap"><table>
        <thead><tr><th>Request</th><th>Type</th><th>Customer</th><th>Amount</th><th>Status</th><th>Expires</th><th></th></tr></thead>
        <tbody>
        @foreach ($pendingRequests as $paymentRequest)
            <tr>
                <td><small title="{{ $paymentRequest->public_id }}">{{ \Illuminate\Support\Str::limit($paymentRequest->public_id, 18) }}</small></td>
                <td>
                    @if ($paymentRequest->isStandalone())
                        <a href="{{ route('admin.payments.requests.show', $paymentRequest) }}">Standalone request</a>
                    @elseif ($paymentRequest->application)
                        <a href="{{ route('admin.applications.show', $paymentRequest->application) }}">Application {{ $paymentRequest->application->reference_number }}</a>
                    @else
                        Application request
                    @endif
                </td>
                <td>{{ $paymentRequest->recipientName() }}@if ($paymentRequest->isStandalone())<br><small>{{ $paymentRequest->customer_email }}</small>@endif</td>
                <td>BHD {{ number_format((float) $paymentRequest->amount, 3) }}</td>
                <td><span class="badge">{{ $paymentRequest->statusLabel() }}</span></td>
                <td>{{ $paymentRequest->expires_at->format('d M Y, H:i') }}</td>
                <td>
                    @if ($paymentRequest->isStandalone())
                        <a href="{{ route('admin.payments.requests.show', $paymentRequest) }}">View</a>
                    @elseif ($paymentRequest->application)
                        <a href="{{ route('admin.applications.show', $paymentRequest->application) }}">View</a>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</section>
@endif

<form class="panel" method="GET" action="{{ route('admin.payments.index') }}"><div class="grid grid-3">
    <div class="field"><label for="q">Search transactions</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Reference, customer, email or phone"></div>
    <div class="field"><label for="type">Payment type</label><select id="type" name="type"><option value="">All payments</option><option value="application" @selected(request('type') === 'application')>Application payments</option><option value="standalone" @selected(request('type') === 'standalone')>Standalone request payments</option><option value="legacy" @selected(request('type') === 'legacy')>Legacy / unlinked payments</option></select></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>@foreach (\App\Models\Payment::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
    <div class="field"><label for="channel">Channel</label><select id="channel" name="channel"><option value="">All channels</option><option value="gateway" @selected(request('channel') === 'gateway')>Online / pending requests</option><option value="offline" @selected(request('channel') === 'offline')>Offline receipt</option></select></div>
    <div class="field"><label for="from">From date</label><input id="from" name="from" type="date" value="{{ request('from') }}"></div>
    <div class="field"><label for="to">To date</label><input id="to" name="to" type="date" value="{{ request('to') }}"></div>
</div><button class="button" type="submit">Filter payments</button> <a href="{{ route('admin.payments.index') }}">Clear filters</a></form>
<div class="table-wrap"><table><thead><tr><th>Transaction</th><th>Customer</th><th>Type / application</th><th>Expected / received</th><th>Status</th><th>Channel</th><th>Date</th></tr></thead><tbody>
@forelse ($payments as $payment)
    @php($linkedApplication = $payment->application ?? $payment->enteredApplication)
    <tr>
        <td><a href="{{ route('admin.payments.show', $payment) }}">{{ $payment->transaction_reference }}</a></td>
        <td>{{ $payment->customer_name }}<br><small>{{ $payment->phone }}</small></td>
        <td>
            @if ($linkedApplication)
                <a href="{{ route('admin.applications.show', $linkedApplication) }}">Application {{ $linkedApplication->reference_number }}</a>
                @unless ($payment->application)
                    <br><small>from entered reference</small>
                @endunless
            @elseif ($payment->paymentRequest?->isStandalone())
                <a href="{{ route('admin.payments.requests.show', $payment->paymentRequest) }}">Standalone request</a>
            @else
                <span>Legacy / unlinked</span>
            @endif
        </td>
        <td>BHD {{ $payment->amount }}<br><small>Received {{ $payment->amount_received }}</small></td>
        <td><span class="badge">{{ str($payment->status)->replace('_', ' ')->title() }}</span></td>
        <td>{{ $payment->channel === 'offline' ? 'Offline' : 'Online request' }}</td>
        <td>{{ $payment->created_at->format('d M Y') }}</td>
    </tr>
@empty<tr><td colspan="7">No payments match these filters.</td></tr>@endforelse
</tbody></table></div>{{ $payments->links() }}
@endsection
