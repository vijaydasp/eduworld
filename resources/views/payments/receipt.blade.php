@extends('layouts.app')
@section('title', 'Payment reference | Edu World Bahrain')
@section('content')
<section class="section"><div class="container"><div class="panel">
    <p class="eyebrow">EDU WORLD · PAYMENT REFERENCE</p>
    @if ($payment->status === 'paid')
        <h1>{{ $payment->channel === 'offline' ? 'Offline payment recorded' : 'Payment successful' }}</h1>
        <p>{{ $payment->channel === 'offline' ? 'Our team has recorded your offline payment.' : 'Your payment has been verified by our payment provider.' }} Thank you for your payment to Edu World.</p>
    @elseif ($payment->status === 'partially_paid')
        <h1>Partial payment recorded</h1><p>We have recorded part of the expected payment. Contact our team about the remaining balance.</p>
    @elseif ($payment->status === 'refunded')
        <h1>Refund recorded</h1><p>A full refund of the received funds has been recorded for this transaction.</p>
    @elseif ($payment->status === 'failed')
        <h1>Payment was not completed</h1><p>The payment provider reported that this payment failed. Contact our team for assistance before trying again.</p>
    @else
        <h1>Your payment request is recorded</h1><p>This payment is pending. No successful payment has been confirmed. Our team can help you complete the next step.</p>
    @endif
    <dl><dt>Transaction reference</dt><dd><strong>{{ $payment->transaction_reference }}</strong></dd>
        @if ($payment->entered_reference)<dt>Your application / invoice reference</dt><dd>{{ $payment->entered_reference }}</dd>@endif
        <dt>Expected amount</dt><dd>BHD {{ $payment->amount }}</dd><dt>Amount received{{ $payment->status === 'refunded' ? ' after refund' : '' }}</dt><dd>BHD {{ $payment->amount_received }}</dd>
        <dt>Status</dt><dd><span class="badge">{{ str($payment->status)->replace('_', ' ')->title() }}</span></dd>
    </dl>
    <p>Keep your transaction reference for future communication. For privacy, this page link expires after 24 hours.</p>
    <a class="button" href="{{ url('/contact') }}">Contact our team</a> <a class="button button-secondary" href="{{ url('/') }}">Back to home</a>
</div></div></section>
@endsection
