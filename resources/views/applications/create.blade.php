@extends('layouts.app')
@section('title', 'Apply Online | Study, Job & Visa Assistance | Edu World Bahrain')
@section('meta_description', 'Apply online with Edu World Bahrain for study abroad, international job assistance, visa services, document support and attestation services.')
@section('content')
<section class="section container">
    <div class="page-intro"><p class="eyebrow">YOUR NEXT CHAPTER STARTS HERE</p><h1>Let's take the next step.</h1><p>Tell us about your plans. Our team will review your requirements and guide you through the next steps.</p></div>
    <div class="application-layout">
        <form action="{{ route('apply.store') }}" method="post" enctype="multipart/form-data" class="panel application-form" data-application-form>
            @csrf
            @if($errors->any())<div class="alert alert-error" role="alert" tabindex="-1"><strong>Please review the information below.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div class="honeypot" aria-hidden="true"><label for="website">Leave this field empty</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
            <fieldset>
                <legend><span class="step-number">01</span> Choose your service</legend>
                <div class="field"><label for="service_type">How can we help? *</label><select id="service_type" name="service_type" required data-service-select>@foreach($services as $key => $service)<option value="{{ $key }}" @selected(old('service_type', $selectedService) === $key)>{{ $service['label'] }}</option>@endforeach</select></div>
            </fieldset>
            <fieldset>
                <legend><span class="step-number">02</span> About you</legend><p>Fields marked * are required.</p>
                <div class="grid form-grid">
                    @foreach(['full_name' => ['Full name', 'text', true, 'name'], 'nationality' => ['Nationality', 'text', true, 'off'], 'date_of_birth' => ['Date of birth', 'date', false, 'bday'], 'current_country' => ['Current country', 'text', true, 'country-name'], 'current_city' => ['Current city', 'text', false, 'address-level2'], 'phone' => ['Mobile / WhatsApp', 'tel', true, 'tel'], 'email' => ['Email', 'email', true, 'email']] as $key => [$label, $type, $required, $autocomplete])
                        <div class="field"><label for="{{ $key }}">{{ $label }}{{ $required ? ' *' : '' }}</label><input id="{{ $key }}" name="{{ $key }}" type="{{ $type }}" value="{{ old($key) }}" autocomplete="{{ $autocomplete }}" @required($required) @if($type === 'date') max="{{ now()->toDateString() }}" @endif aria-invalid="{{ $errors->has($key) ? 'true' : 'false' }}">@error($key)<small class="field-error">{{ $message }}</small>@enderror</div>
                    @endforeach
                    <div class="field"><label for="preferred_contact_method">Preferred contact method</label><select id="preferred_contact_method" name="preferred_contact_method"><option value="">No preference</option>@foreach(['whatsapp'=>'WhatsApp', 'phone'=>'Phone', 'email'=>'Email'] as $key=>$label)<option value="{{ $key }}" @selected(old('preferred_contact_method') === $key)>{{ $label }}</option>@endforeach</select></div>
                </div>
            </fieldset>
            @foreach($services as $serviceKey => $service)
                <fieldset data-service-fields="{{ $serviceKey }}" @if(old('service_type', $selectedService) !== $serviceKey) hidden disabled @endif>
                    <legend><span class="step-number">03</span> {{ $service['label'] }} details</legend>
                    <div class="grid form-grid">
                    @foreach($service['fields'] as $key => $field)
                        <div class="field {{ in_array($field['type'], ['textarea', 'checkboxes']) ? 'full-width' : '' }}">
                            @if($field['type'] === 'checkboxes')
                                <fieldset><legend>{{ $field['label'] }}{{ ($field['required'] ?? false) ? ' *' : '' }}</legend>@foreach($field['options'] as $option)<label class="checkbox-label"><input type="checkbox" name="details[{{ $key }}][]" value="{{ $option }}" @checked(in_array($option, (array) old('details.'.$key, [])))> {{ $option }}</label>@endforeach</fieldset>
                            @else
                                <label for="{{ $serviceKey }}_{{ $key }}">{{ $field['label'] }}{{ ($field['required'] ?? false) ? ' *' : '' }}</label>
                                @if($field['type'] === 'select')
                                    <select id="{{ $serviceKey }}_{{ $key }}" name="details[{{ $key }}]" @required($field['required'] ?? false)><option value="">Select an option</option>@foreach($field['options'] as $option)<option value="{{ $option }}" @selected(old('details.'.$key) === $option)>{{ $option }}</option>@endforeach</select>
                                @elseif($field['type'] === 'textarea')
                                    <textarea id="{{ $serviceKey }}_{{ $key }}" name="details[{{ $key }}]" rows="4" maxlength="5000" @required($field['required'] ?? false)>{{ old('details.'.$key) }}</textarea>
                                @else
                                    <input id="{{ $serviceKey }}_{{ $key }}" type="{{ $field['type'] }}" name="details[{{ $key }}]" value="{{ old('details.'.$key) }}" @required($field['required'] ?? false) @if($field['type'] === 'number') min="{{ $field['min'] }}" max="{{ $field['max'] }}" step="1" @endif>
                                @endif
                            @endif
                            @error('details.'.$key)<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                    @endforeach
                    </div>
                    @if($service['uploads'])
                        <h3>Supporting documents</h3><p>Upload PDF, JPG or PNG files, up to 10 MB each. Maximum 5 files per category and 12 files in total. Documents are stored privately and accessed by authorized staff.</p>
                        <div class="grid form-grid">@foreach($service['uploads'] as $kind=>$label)<div class="field"><label for="{{ $serviceKey }}_{{ $kind }}">{{ $label }}</label><input type="file" id="{{ $serviceKey }}_{{ $kind }}" name="documents[{{ $kind }}][]" accept=".pdf,.jpg,.jpeg,.png" multiple></div>@endforeach</div>
                    @endif
                </fieldset>
            @endforeach
            <fieldset><legend><span class="step-number">04</span> Confirm and submit</legend>
                <label class="checkbox-label"><input type="checkbox" name="consent_accuracy" value="1" @checked(old('consent_accuracy')) required> <span>I confirm that the information provided is accurate to the best of my knowledge. *</span></label>
                <label class="checkbox-label"><input type="checkbox" name="consent_processing" value="1" @checked(old('consent_processing')) required> <span>I consent to Edu World processing the information and documents I submit for the purpose of handling my enquiry/application and providing the requested service, subject to the <a href="{{ url('/privacy-policy') }}" target="_blank" rel="noopener">Privacy Policy</a>. *</span></label>
                <x-captcha />
                <p class="field-error" data-upload-error role="alert" hidden></p><button type="submit" class="button" data-submit-button>Submit application →</button><p role="status" data-submit-status hidden>Submitting your application. Please keep this page open.</p>
            </fieldset>
        </form>
        <aside class="panel application-aside"><p class="eyebrow">HERE TO ASSIST YOU</p><h2>A clearer path forward.</h2><ol class="steps-list"><li><strong>Tell us your plans</strong><p>Choose your service and share the relevant information.</p></li><li><strong>Receive your reference</strong><p>Keep your unique application number for future communication.</p></li><li><strong>Speak with our team</strong><p>We will review your requirements and help you understand the next steps.</p></li></ol><hr><p>Need help with the form?</p><a href="https://wa.me/{{ preg_replace('/\D/', '', \App\Models\Setting::getValue('whatsapp', '97333640001')) }}" class="button button-secondary" target="_blank" rel="noopener">Chat on WhatsApp</a><p class="muted">Admission, employment and visa decisions remain with the relevant institutions, employers and authorities.</p></aside>
    </div>
</section>
<script src="{{ asset('js/application-form.js') }}" defer></script>
@endsection
