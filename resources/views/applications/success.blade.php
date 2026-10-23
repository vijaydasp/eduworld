@extends('layouts.app')
@section('title', 'Application Received | Edu World Bahrain')
@section('content')
<section class="section container"><div class="panel success-panel"><p class="eyebrow">THANK YOU FOR CHOOSING EDU WORLD</p><h1>Your application has been received.</h1><p>Application reference</p><p class="reference-number"><strong>{{ $application->reference_number }}</strong></p><p>{{ $application->serviceLabel() }}</p><p>Please keep your reference number for future communication. Our team will review your submission and contact you if additional information or documentation is required.</p><p>If a service fee applies, our team will confirm the amount before you proceed to payment.</p><div class="button-row"><a class="button" href="{{ url('/pay') }}">Pay an agreed service fee</a><a class="button button-secondary" href="{{ url('/') }}">Back to home</a></div></div></section>
@endsection
