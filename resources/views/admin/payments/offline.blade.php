@extends('layouts.admin')
@section('title', 'Record offline receipt')
@section('content')
<h1>Record offline receipt</h1><p>For payments already received by bank transfer or in person. The receipt is marked as an offline administrative record and the action is audited.</p>
<form class="panel" method="POST" action="{{ route('admin.payments.offline.store') }}">@csrf
    <div class="grid grid-2">
        <div class="field"><label for="customer_name">Customer name *</label><input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" maxlength="150" required></div>
        <div class="field"><label for="phone">Phone *</label><input id="phone" name="phone" value="{{ old('phone') }}" type="tel" required></div>
        <div class="field"><label for="email">Email</label><input id="email" name="email" value="{{ old('email') }}" type="email"></div>
        <div class="field"><label for="application_reference">Application reference (optional)</label><input id="application_reference" name="application_reference" value="{{ old('application_reference') }}"></div>
        <div class="field"><label for="service">Service *</label><select id="service" name="service" required>@foreach (config('applications.services') as $key => $service)<option value="{{ $key }}" @selected(old('service') === $key)>{{ $service['label'] }}</option>@endforeach</select></div>
        <div class="field"><label for="offline_reference">Bank / cash receipt reference *</label><input id="offline_reference" name="offline_reference" value="{{ old('offline_reference') }}" maxlength="150" required></div>
        <div class="field"><label for="amount">Total expected (BHD) *</label><input id="amount" name="amount" value="{{ old('amount') }}" inputmode="decimal" placeholder="0.000" required></div>
        <div class="field"><label for="amount_received">Amount received (BHD) *</label><input id="amount_received" name="amount_received" value="{{ old('amount_received') }}" inputmode="decimal" placeholder="0.000" required></div>
    </div>
    <div class="field"><label for="remarks">Receipt details / verification notes *</label><textarea id="remarks" name="remarks" required minlength="5" maxlength="2000">{{ old('remarks') }}</textarea></div>
    <button class="button" type="submit">Record received funds</button> <a href="{{ route('admin.payments.index') }}">Cancel</a>
</form>
@endsection
