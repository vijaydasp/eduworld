@extends('layouts.app')
@section('title', 'Secure payment request | Edu World Bahrain')
@section('content')
<section class="section"><div class="container">
    <p class="eyebrow">EDU WORLD · PAYMENT REQUEST</p>
    @if (($linkBlocked ?? null) === 'expired')
        <h1>Payment Link Expired</h1>
        <div class="alert alert-error" role="alert">This payment link has expired. Please contact Eduworld or request a new payment link from the administrator.</div>
    @elseif (($linkBlocked ?? null) === 'cancelled')
        <h1>Payment Link Cancelled</h1>
        <div class="alert alert-error" role="alert">This payment link was cancelled. Please contact Eduworld or request a new payment link from the administrator.</div>
    @else
        <h1>Your finalized payment request</h1>
    @endif
    @if (session('payment_error'))
        <div class="alert alert-error" role="alert">{{ session('payment_error') }}</div>
    @endif
    @if ($paymentRequest->isStandalone())
        <p>These details were confirmed with our team for this payment request.</p>
    @else
        <p>These details were confirmed with our team for application <strong>{{ $paymentRequest->application?->reference_number }}</strong>.</p>
    @endif

    <div class="grid grid-2">
        <section class="panel">
            <h2>Payment summary</h2>
            <dl class="detail-list">
                @if ($paymentRequest->isStandalone())
                    <dt>Customer name</dt><dd>{{ $paymentRequest->recipientName() }}</dd>
                @else
                    <dt>Application reference</dt><dd>{{ $paymentRequest->application?->reference_number }}</dd>
                    <dt>Student name</dt><dd>{{ $paymentRequest->application?->full_name }}</dd>
                    <dt>Service / course</dt><dd>{{ $paymentRequest->application?->serviceLabel() }}</dd>
                @endif
                <dt>Payment description</dt><dd class="preserve-lines">{{ $paymentRequest->description }}</dd>
                <dt>Agreed amount</dt><dd><strong>{{ $paymentRequest->currency }} {{ number_format((float) $paymentRequest->amount, 3) }}</strong></dd>
                <dt>Currency</dt><dd>{{ $paymentRequest->currency }} · Bahraini dinar</dd>
                <dt>Link expires</dt><dd>{{ $paymentRequest->expires_at->copy()->setTimezone(config('payments.display_timezone', 'Asia/Kolkata'))->format('d M Y, H:i') }} · Asia/Kolkata</dd>
                @if ($paymentRequest->due_at)<dt>Payment due</dt><dd>{{ $paymentRequest->due_at->copy()->setTimezone(config('payments.display_timezone', 'Asia/Kolkata'))->format('d M Y, H:i') }} · Asia/Kolkata</dd>@endif
                <dt>Status</dt><dd><span class="badge">{{ $paymentRequest->statusLabel() }}</span></dd>
            </dl>

            @if ($paymentRequest->status === 'paid')
                <div class="alert alert-success" role="status">This payment request has already been paid. No further payment is required for this request.</div>
                @if ($receiptUrl)
                    <a class="button" href="{{ $receiptUrl }}">View payment receipt</a>
                @endif
            @else
                @if ($paymentRequest->status === 'sent')
                    <form method="POST" action="{{ \Illuminate\Support\Facades\URL::signedRoute('pay.request.pay', ['paymentRequest' => $paymentRequest]) }}">
                        @csrf
                        <button class="button" type="submit">Pay securely via AFS</button>
                    </form>
                    <p>The amount, customer details and payment description cannot be changed on this page.</p>
                @endif
            @endif
        </section>

        <aside class="panel">
            <span class="badge">BHD · Bahraini dinar</span>
            @if ($gatewayAvailable)
                <h2>Your payment, protected</h2>
                <p>You will continue to our payment provider to enter your card details. Edu World never sees or stores your card number or security code.</p>
            @else
                <h2>Online checkout is not currently available</h2>
                <p>Our team can help you complete this payment. Please contact us using the details below.</p>
            @endif
            <p>Your payment is confirmed only after the payment provider verifies the transaction.</p>
            <h3>Need help?</h3>
            <p>Contact Edu World at {{ \App\Models\Setting::getValue('email', 'info@eduworld.bh') }} or {{ \App\Models\Setting::getValue('phone', '+973 33640001') }}.</p>
            <a class="button button-secondary" href="{{ url('/contact') }}">Contact our team</a>
        </aside>
    </div>
</div></section>
@endsection
