@extends('layouts.app')
@section('title', 'Make a Secure Payment | Edu World Bahrain')
@section('content')
<section class="section"><div class="container">
    <p class="eyebrow">EDU WORLD · PAYMENTS</p>
    <h1>Make a secure payment</h1>
    <p>Use the service fee agreed with our team and include your application or invoice reference.</p>
    <div class="grid grid-2">
        <form class="panel" method="POST" action="{{ route('pay.store') }}">
            @csrf
            @if ($errors->any())<div class="alert" role="alert"><strong>Please check your details.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div class="grid grid-2">
                <div class="field"><label for="customer_name">Full name *</label><input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" maxlength="150" autocomplete="name" required></div>
                <div class="field"><label for="phone">WhatsApp / mobile number *</label><input id="phone" name="phone" value="{{ old('phone') }}" maxlength="30" type="tel" autocomplete="tel" required></div>
                <div class="field"><label for="email">Email</label><input id="email" name="email" value="{{ old('email') }}" type="email" autocomplete="email" maxlength="255"></div>
                <div class="field"><label for="entered_reference">Application / invoice reference</label><input id="entered_reference" name="entered_reference" value="{{ old('entered_reference', request('reference')) }}" maxlength="100"></div>
                <div class="field"><label for="service">Service *</label><select id="service" name="service" required><option value="">Select your service</option>@foreach (config('applications.services') as $key => $service)<option value="{{ $key }}" @selected(old('service', request('service')) === $key)>{{ $service['label'] }}</option>@endforeach</select></div>
                <div class="field"><label for="amount">Amount (BHD) *</label><input id="amount" name="amount" value="{{ old('amount') }}" inputmode="decimal" placeholder="0.000" pattern="[0-9]+(\.[0-9]{1,3})?" required><small>Enter the fee confirmed by Edu World, with up to 3 decimal places.</small></div>
            </div>
            <div class="field"><label for="remarks">Payment remarks</label><textarea id="remarks" name="remarks" rows="3" maxlength="2000">{{ old('remarks') }}</textarea></div>
            <div hidden aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
            <div class="field"><label><input type="checkbox" name="terms" value="1" @checked(old('terms')) required> I agree to the <a href="{{ url('/payment-terms') }}">payment terms</a> and <a href="{{ url('/refund-policy') }}">refund policy</a>.</label></div>
            <x-captcha />
            <button class="button" type="submit">{{ $gatewayAvailable ? 'Proceed to secure payment' : 'Send payment request' }}</button>
        </form>
        <aside class="panel">
            <span class="badge">BHD · Bahraini dinar</span>
            @if ($gatewayAvailable)
                <h2>Your payment, protected</h2><p>You will continue to our payment provider to enter payment details. Confirmation is issued after the provider verifies your payment.</p>
            @else
                <h2>Online checkout is not yet available</h2><p>Send your details and our team will help you arrange payment. Submitting this form records a pending request. It does not collect or transfer any money.</p>
            @endif
            <p>Please do not include card numbers, passwords or bank security codes in your remarks.</p>
            <h3>Need help with a fee?</h3><p>Contact Edu World before paying if your service fee or reference is unclear.</p>
            <a class="button button-secondary" href="{{ url('/contact') }}">Contact our team</a>
        </aside>
    </div>
</div></section>
@endsection
