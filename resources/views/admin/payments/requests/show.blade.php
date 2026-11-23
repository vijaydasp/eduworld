@extends('layouts.admin')
@section('title', 'Standalone payment request')
@section('content')
<p><a href="{{ route('admin.payments.requests.index') }}">← All standalone payment requests</a></p>
<h1>Standalone payment request</h1>
<p>{{ $paymentRequest->description }}</p>

<div class="grid grid-2">
    <section class="panel">
        <h2>Request details</h2>
        <dl class="detail-list">
            <dt>Request ID</dt><dd><small>{{ $paymentRequest->public_id }}</small></dd>
            <dt>Customer name</dt><dd>{{ $paymentRequest->customer_name }}</dd>
            <dt>Customer email</dt><dd>{{ $paymentRequest->customer_email }}</dd>
            <dt>Customer phone</dt><dd>{{ $paymentRequest->customer_phone ?: 'Not supplied' }}</dd>
            <dt>Payment description</dt><dd class="preserve-lines">{{ $paymentRequest->description }}</dd>
            <dt>Amount</dt><dd><strong>BHD {{ number_format((float) $paymentRequest->amount, 3) }}</strong></dd>
            <dt>Currency</dt><dd>{{ $paymentRequest->currency }} · Bahraini dinar</dd>
            <dt>Status</dt><dd><span class="badge">{{ $paymentRequest->statusLabel() }}</span></dd>
            <dt>Created</dt><dd>{{ $paymentRequest->created_at->format('d M Y, H:i') }}</dd>
            <dt>Email sent</dt><dd>{{ $paymentRequest->sent_at?->format('d M Y, H:i') ?? 'Not sent' }}</dd>
            <dt>Expires</dt><dd>{{ $paymentRequest->expires_at->copy()->setTimezone(config('payments.display_timezone', 'Asia/Kolkata'))->format('d M Y, H:i') }} · Asia/Kolkata</dd>
            @if ($paymentRequest->due_at)<dt>Payment due</dt><dd>{{ $paymentRequest->due_at->copy()->setTimezone(config('payments.display_timezone', 'Asia/Kolkata'))->format('d M Y, H:i') }} · Asia/Kolkata</dd>@endif
            <dt>Paid</dt><dd>{{ $paymentRequest->paid_at?->format('d M Y, H:i') ?? '—' }}</dd>
            @if ($paymentRequest->createdBy)<dt>Created by</dt><dd>{{ $paymentRequest->createdBy->name }}</dd>@endif
        </dl>

        @if ($paymentRequest->status === 'sent' && $paymentRequest->isPayable())
            <form method="post" action="{{ route('admin.payments.requests.resend', $paymentRequest) }}">@csrf<button class="button button-secondary" type="submit">Resend payment email</button></form>
            <form method="post" action="{{ route('admin.payments.requests.cancel', $paymentRequest) }}">@csrf<button class="button button-secondary" type="submit">Cancel unpaid request</button></form>
        @elseif ($paymentRequest->status === 'sent')
            <div class="alert" role="status">This request has expired. Extend or replace it below.</div>
        @endif

        @if (in_array($paymentRequest->status, ['sent', 'expired'], true))
            <h3>Manage expiry</h3>
            <form method="post" action="{{ route('admin.payments.requests.extend', $paymentRequest) }}">
                @csrf
                <div class="field"><label for="extend_expiry_option">New expiry</label><select id="extend_expiry_option" name="expiry_option" required>@foreach(['1_hour'=>'1 hour','6_hours'=>'6 hours','12_hours'=>'12 hours','24_hours'=>'24 hours','2_days'=>'2 days','3_days'=>'3 days','7_days'=>'7 days','custom'=>'Custom date and time'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                <div class="field"><label for="extend_custom_expires_at">Custom expiry date and time</label><input id="extend_custom_expires_at" name="custom_expires_at" type="datetime-local"></div>
                <div class="field"><label for="extend_due_at">Optional payment due date</label><input id="extend_due_at" name="due_at" type="datetime-local" value="{{ $paymentRequest->due_at?->copy()->setTimezone(config('payments.display_timezone', 'Asia/Kolkata'))->format('Y-m-d\TH:i') }}"></div>
                <button class="button button-secondary" type="submit">Extend &amp; resend link</button>
            </form>
        @endif
        @if (in_array($paymentRequest->status, ['sent', 'expired', 'cancelled'], true))
            <form method="post" action="{{ route('admin.payments.requests.replace', $paymentRequest) }}">
                @csrf
                <div class="field"><label for="replace_expiry_option">Replacement expiry</label><select id="replace_expiry_option" name="expiry_option" required>@foreach(['1_hour'=>'1 hour','6_hours'=>'6 hours','12_hours'=>'12 hours','24_hours'=>'24 hours','2_days'=>'2 days','3_days'=>'3 days','7_days'=>'7 days','custom'=>'Custom date and time'] as $value => $label)<option value="{{ $value }}" @selected($value === '7_days')>{{ $label }}</option>@endforeach</select></div>
                <div class="field"><label for="replace_custom_expires_at">Custom expiry date and time</label><input id="replace_custom_expires_at" name="custom_expires_at" type="datetime-local"></div>
                <div class="field"><label for="replace_due_at">Optional payment due date</label><input id="replace_due_at" name="due_at" type="datetime-local" value="{{ $paymentRequest->due_at?->copy()->setTimezone(config('payments.display_timezone', 'Asia/Kolkata'))->format('Y-m-d\TH:i') }}"></div>
                <button class="button" type="submit">Generate replacement link</button>
            </form>
        @endif
    </section>

    <section class="panel">
        <h2>Payment transaction</h2>
        @if ($paymentRequest->payment)
            @php($requestPayment = $paymentRequest->payment)
            <dl class="detail-list">
                <dt>Transaction reference</dt><dd><a href="{{ route('admin.payments.show', $requestPayment) }}">{{ $requestPayment->transaction_reference }}</a></dd>
                <dt>Status</dt><dd><span class="badge">{{ str($requestPayment->status)->replace('_', ' ')->title() }}</span></dd>
                <dt>Expected amount</dt><dd>BHD {{ $requestPayment->amount }}</dd>
                <dt>Received amount</dt><dd>BHD {{ $requestPayment->amount_received }}</dd>
                <dt>Provider</dt><dd>{{ $requestPayment->gateway }}</dd>
                <dt>Provider reference</dt><dd>{{ $requestPayment->gateway_reference ?: 'Not available' }}</dd>
            </dl>
            <p><a class="button button-secondary" href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('pay.receipt', now()->addMinutes(30), ['payment' => $requestPayment->id]) }}">View / download receipt</a></p>
        @else
            <p>No payment has been started for this request yet. The customer starts checkout from the secure email link.</p>
        @endif
        <h3>Customer payment link</h3>
        <p>The customer receives a signed link by email. You can resend it while the request is active.</p>
    </section>
</div>
@endsection
