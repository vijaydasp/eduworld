@extends('layouts.admin')
@section('title', 'Create payment request')
@section('content')
<p><a href="{{ route('admin.payments.requests.index') }}">← All standalone payment requests</a></p>
<h1>Create payment request</h1>
<p>Send a secure payment link to a customer without an application. The amount and customer details are fixed once the request is created.</p>

<form class="panel" method="POST" action="{{ route('admin.payments.requests.store') }}">@csrf
    <div class="grid grid-2">
        <div class="field"><label for="customer_name">Customer name *</label><input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" maxlength="150" required></div>
        <div class="field"><label for="customer_email">Customer email *</label><input id="customer_email" name="customer_email" type="email" value="{{ old('customer_email') }}" maxlength="254" required><small>The secure payment link is emailed to this address.</small></div>
        <div class="field"><label for="customer_phone">Customer phone (optional)</label><input id="customer_phone" name="customer_phone" type="tel" value="{{ old('customer_phone') }}" maxlength="30"></div>
        <div class="field"><label for="amount">Amount (BHD) *</label><input id="amount" name="amount" value="{{ old('amount') }}" inputmode="decimal" placeholder="0.000" pattern="[0-9]+(\.[0-9]{1,3})?" required><small>Up to three decimal places, between 0.001 and {{ config('payments.maximum_amount') }}.</small></div>
        <div class="field"><label for="currency">Currency</label><input id="currency" name="currency" value="BHD" readonly><small>All standalone requests use Bahraini dinar.</small></div>
        <div class="field"><label for="validity_days">Payment link validity (days) *</label><input id="validity_days" name="validity_days" type="number" value="{{ old('validity_days', 7) }}" min="1" max="30" required></div>
    </div>
    <div class="field"><label for="description">Payment description *</label><input id="description" name="description" value="{{ old('description') }}" maxlength="255" placeholder="e.g. Consultation and document review fee" required></div>
    <div class="field"><label><input type="checkbox" name="confirmed" value="1" @checked(old('confirmed')) required> I confirm the amount, customer details and description are correct.</label></div>
    <button class="button" type="submit">Create &amp; Send Payment Request</button> <a href="{{ route('admin.payments.index') }}">Cancel</a>
</form>
@endsection
