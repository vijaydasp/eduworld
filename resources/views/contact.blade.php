@extends('layouts.app')
@section('title', 'Contact Edu World Bahrain | Study, Job & Visa Assistance')
@section('meta_description', 'Contact Edu World in Segaya, Manama, Bahrain for study abroad, job assistance, visa services, supporting documents and attestation assistance.')
@section('content')
<section class="section container"><div class="page-intro"><p class="eyebrow">LET'S TALK ABOUT YOUR PLANS</p><h1>A conversation can open a world of possibilities.</h1><p>From your first question to your next step, our team is here to assist you.</p></div>
    <div class="grid contact-grid">
        <div class="panel"><h2>Visit. Call. Connect.</h2><p><strong>EDU WORLD</strong><br>{{ \App\Models\Setting::getValue('address', 'Segaya, Manama, Kingdom of Bahrain') }}<br>Behind / Near Max & Megamart</p><p><a href="mailto:{{ \App\Models\Setting::getValue('email', 'info@eduworld.bh') }}">{{ \App\Models\Setting::getValue('email', 'info@eduworld.bh') }}</a></p><p><a href="tel:{{ preg_replace('/[^+0-9]/', '', \App\Models\Setting::getValue('phone', '+973 33640001')) }}">{{ \App\Models\Setting::getValue('phone', '+973 33640001') }}</a><br><a href="tel:{{ preg_replace('/[^+0-9]/', '', \App\Models\Setting::getValue('phone_secondary', '+973 17264002')) }}">{{ \App\Models\Setting::getValue('phone_secondary', '+973 17264002') }}</a></p><a class="button" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D/', '', \App\Models\Setting::getValue('whatsapp', '97333640001')) }}">Chat on WhatsApp</a>
            <hr><h3>Looking for international candidates?</h3><p>If your organization requires skilled or semi-skilled candidates, contact us to discuss your recruitment requirements.</p><a href="{{ route('apply', ['service'=>'employer_request']) }}">Request manpower →</a>
            <div class="social-links">@foreach(['facebook'=>'Facebook', 'instagram'=>'Instagram', 'youtube'=>'YouTube'] as $key=>$label)@if($social = \App\Models\Setting::getValue($key.'_url'))<a href="{{ $social }}" target="_blank" rel="noopener">{{ $label }}</a>@endif
@endforeach</div>
        </div>
        <form class="panel" action="{{ route('contact.store') }}" method="post">@csrf<h2>Send us an enquiry</h2>
            @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert alert-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div class="honeypot" aria-hidden="true"><label for="website">Leave this field empty</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
            <div class="field"><label for="full_name">Full name *</label><input id="full_name" name="full_name" value="{{ old('full_name') }}" autocomplete="name" maxlength="150" required></div>
            <div class="field"><label for="phone">Mobile / WhatsApp *</label><input type="tel" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" maxlength="30" required></div>
            <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" maxlength="254"></div>
            <div class="field"><label for="service_type">Service required *</label><select id="service_type" name="service_type" required><option value="">Choose a service</option>@foreach($services as $key=>$service)<option value="{{ $key }}" @selected(old('service_type', request('service')) === $key)>{{ $service['label'] }}</option>@endforeach</select></div>
            <div class="field"><label for="message">Message *</label><textarea id="message" name="message" rows="5" minlength="10" maxlength="5000" required>{{ old('message') }}</textarea></div>
            <p>We use your information to respond to your enquiry. Read our <a href="{{ url('/privacy-policy') }}">Privacy Policy</a>.</p><x-captcha /><button class="button" type="submit">Send enquiry →</button>
        </form>
    </div>
    <div class="panel"><h2>Visit our office</h2><p>{{ \App\Models\Setting::getValue('business_hours') }}</p>@if(\App\Models\Setting::getValue('map_url'))<iframe class="office-map" title="Edu World office area in Segaya, Manama, Bahrain" src="{{ \App\Models\Setting::getValue('map_url') }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>@endif<p>For faster assistance, please contact us on WhatsApp before visiting regarding document requirements and availability.</p></div>
</section>
@endsection
