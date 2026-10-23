@extends('layouts.admin')
@section('title', 'Payments')
@section('content')
<h1>Payments</h1><p>Review verified gateway transactions, pending payment requests and recorded offline receipts.</p>
@can('manage-settings')<p><a class="button" href="{{ route('admin.payments.offline.create') }}">Record offline receipt</a></p>@endcan
<form class="panel" method="GET" action="{{ route('admin.payments.index') }}"><div class="grid grid-3">
    <div class="field"><label for="q">Search transactions</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Reference, customer, email or phone"></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>@foreach (\App\Models\Payment::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
    <div class="field"><label for="channel">Channel</label><select id="channel" name="channel"><option value="">All channels</option><option value="gateway" @selected(request('channel') === 'gateway')>Online / pending requests</option><option value="offline" @selected(request('channel') === 'offline')>Offline receipt</option></select></div>
    <div class="field"><label for="from">From date</label><input id="from" name="from" type="date" value="{{ request('from') }}"></div>
    <div class="field"><label for="to">To date</label><input id="to" name="to" type="date" value="{{ request('to') }}"></div>
</div><button class="button" type="submit">Filter payments</button> <a href="{{ route('admin.payments.index') }}">Clear filters</a></form>
<div class="table-wrap"><table><thead><tr><th>Transaction</th><th>Customer</th><th>Application</th><th>Expected / received</th><th>Status</th><th>Channel</th><th>Date</th></tr></thead><tbody>
@forelse ($payments as $payment)
    <tr><td><a href="{{ route('admin.payments.show', $payment) }}">{{ $payment->transaction_reference }}</a></td><td>{{ $payment->customer_name }}<br><small>{{ $payment->phone }}</small></td><td>{{ $payment->application?->reference_number ?? 'Unlinked' }}</td><td>BHD {{ $payment->amount }}<br><small>Received {{ $payment->amount_received }}</small></td><td><span class="badge">{{ str($payment->status)->replace('_', ' ')->title() }}</span></td><td>{{ $payment->channel === 'offline' ? 'Offline' : 'Online request' }}</td><td>{{ $payment->created_at->format('d M Y') }}</td></tr>
@empty<tr><td colspan="7">No payments match these filters.</td></tr>@endforelse
</tbody></table></div>{{ $payments->links() }}
@endsection
