@extends('layouts.app')
@section('title', 'Secure card payment | Edu World Bahrain')
@push('head')
    <script>
        var wpwlOptions = {
            style: "card",
            locale: "en",
            onReady: function() {
                var loading = document.getElementById('copyandpay-loading');
                if (loading) {
                    loading.hidden = true;
                }
            },
            onError: function() {
                var loading = document.getElementById('copyandpay-loading');
                var error = document.getElementById('copyandpay-error');
                if (loading) {
                    loading.hidden = true;
                }
                if (error) {
                    error.hidden = false;
                }
            },
            onAfterSubmit: function() {
                var buttons = document.querySelectorAll('.wpwl-button-pay');
                buttons.forEach(function(button) {
                    button.disabled = true;
                    button.setAttribute('aria-disabled', 'true');
                });
            }
        };
    </script>
    <script src="{{ $scriptUrl }}" @if ($scriptIntegrity) integrity="{{ $scriptIntegrity }}" @endif crossorigin="anonymous" onerror="document.getElementById('copyandpay-loading').hidden = true; document.getElementById('copyandpay-error').hidden = false;"></script>
@endpush
@section('content')
<section class="section"><div class="container">
    <p class="eyebrow">EDU WORLD · SECURE PAYMENT</p>
    <h1>Complete your payment</h1>
    <p>Enter your card details below. Payment is processed by AFS. Edu World never sees or stores your card number or security code.</p>
    <div class="grid grid-2">
        <div class="panel">
            <p id="copyandpay-loading" role="status" aria-live="polite">Loading the secure payment form…</p>
            <div id="copyandpay-error" class="alert error" role="alert" hidden>
                The secure payment form could not be loaded. Check your connection and <a href="{{ url()->current() }}">reload this page</a> to try again.
            </div>
            <form action="{{ $resultUrl }}" class="paymentWidgets" data-brands="{{ $brands }}"></form>
            <noscript><p class="alert" role="alert">JavaScript is required to display the secure payment form. Please enable JavaScript or contact our team for help.</p></noscript>
        </div>
        <aside class="panel">
            <span class="badge">BHD · Bahraini dinar</span>
            <dl>
                <dt>Transaction reference</dt><dd><strong>{{ $payment->transaction_reference }}</strong></dd>
                @if ($payment->entered_reference)<dt>Your application / invoice reference</dt><dd>{{ $payment->entered_reference }}</dd>@endif
                <dt>Amount</dt><dd>BHD {{ $payment->amount }}</dd>
                <dt>Service</dt><dd>{{ $payment->serviceLabel() }}</dd>
            </dl>
            <p>After the payment is processed you will return to Edu World. Your confirmation appears only after the provider verifies the payment.</p>
            <p>If the form does not appear, wait a moment and reload this page. For help, <a href="{{ url('/contact') }}">contact our team</a>.</p>
        </aside>
    </div>
</div></section>
@endsection
