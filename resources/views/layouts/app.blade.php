@php
$company = \App\Models\Setting::getValue('company_name','Edu World');
$defaultTitle = \App\Models\Setting::getValue('default_seo_title','Edu World Bahrain');
$description = html_entity_decode(trim($__env->yieldContent('meta_description', $__env->yieldContent('description', \App\Models\Setting::getValue('default_meta_description')))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$canonical = rtrim(config('app.url'),'/').'/'.ltrim(request()->path()==='/'?'':request()->path(),'/');
$logo = \App\Models\Setting::getValue('logo_path');
$favicon = \App\Models\Setting::getValue('favicon_path');
@endphp
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', $defaultTitle)</title>
<meta name="description" content="{{ $description }}"><link rel="canonical" href="{{ $canonical }}">
<meta property="og:title" content="@yield('title', $defaultTitle)"><meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}"><meta property="og:type" content="@yield('og_type','website')"><meta property="og:site_name" content="{{ $company }}">
<meta name="twitter:card" content="summary"><meta name="twitter:title" content="@yield('title',$defaultTitle)"><meta name="twitter:description" content="{{ $description }}">
@hasSection('og_image')<meta property="og:image" content="@yield('og_image')"><meta name="twitter:image" content="@yield('og_image')">@endif
<link rel="icon" href="{{ $favicon ? asset('storage/'.$favicon) : asset('images/favicon.svg') }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
@vite(['resources/css/app.css','resources/js/app.js'])
@stack('head')
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'LocalBusiness','name'=>$company,'url'=>config('app.url'),'telephone'=>\App\Models\Setting::getValue('phone'),'email'=>\App\Models\Setting::getValue('email'),'address'=>['@type'=>'PostalAddress','streetAddress'=>\App\Models\Setting::getValue('address'),'addressLocality'=>'Manama','addressCountry'=>'BH']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES) !!}</script>
</head><body>
<a class="skip-link" href="#main">Skip to content</a>
<div class="topbar"><div class="container topbar-inner"><span><x-icon name="pin"/> Based in Bahrain. Connected to the world.</span><div><a href="mailto:{{ \App\Models\Setting::getValue('email') }}">{{ \App\Models\Setting::getValue('email') }}</a><a href="tel:{{ preg_replace('/\s/','',\App\Models\Setting::getValue('phone')) }}">{{ \App\Models\Setting::getValue('phone') }}</a></div></div></div>
<header class="site-header"><div class="container header-inner">
<a href="{{ route('home') }}" class="brand" aria-label="{{ $company }} home"><img src="{{ $logo ? asset('storage/'.$logo) : asset('images/logo-inverse.svg') }}" alt="{{ $company }}" width="123" height="67"></a>
<button class="menu-toggle" type="button" aria-controls="main-navigation" aria-expanded="false" aria-label="Open navigation"><span></span><span></span><span></span></button>
<a class="button mobile-apply" href="{{ route('apply') }}">Apply now</a>
<nav id="main-navigation" aria-label="Main navigation">
@foreach(['home'=>'Home','services'=>'Services','apply'=>'Apply online','news'=>'News & gallery','contact'=>'Contact us'] as $name=>$label)<a class="{{ request()->routeIs($name,$name.'.*')?'active':'' }}" href="{{ route($name) }}" @if(request()->routeIs($name)) aria-current="page" @endif>{{ $label }}</a>@endforeach
<a class="button small" href="{{ route('pay') }}">Pay now <x-icon name="arrow"/></a>
<a class="nav-whatsapp" href="{{ \App\Models\Setting::whatsapp('Hello Edu World, I would like to discuss your services.') }}" target="_blank" rel="noopener"><x-icon name="chat"/><span>WhatsApp</span></a>
</nav></div></header>
<main id="main">
@if(session('success'))<div class="container alert success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="container alert error" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main>
<footer class="site-footer"><div class="container">
<div class="footer-top"><div class="footer-brand"><img src="{{ $logo ? asset('storage/'.$logo) : asset('images/logo-inverse.svg') }}" width="135" height="73" alt="{{ $company }}"><h3>Your Gateway to<br>Global Opportunities</h3><p>Study · Work · Travel<br>We're here to assist you.</p></div>
<div><h4>Explore</h4>@foreach(['home'=>'Home','services'=>'Our services','apply'=>'Apply online','news'=>'News & gallery','contact'=>'Contact us','pay'=>'Pay online'] as $name=>$label)<a href="{{ route($name) }}">{{ $label }}</a>@endforeach</div>
<div><h4>Our services</h4>@foreach(config('service-content') as $key=>$service)<a href="{{ route('services') }}#{{ $key }}">{{ $service['label'] }}</a>@endforeach</div>
<div class="footer-contact"><h4>Let's connect</h4><p>{{ \App\Models\Setting::getValue('address') }}</p><a href="mailto:{{ \App\Models\Setting::getValue('email') }}">{{ \App\Models\Setting::getValue('email') }}</a><a href="tel:{{ preg_replace('/\s/','',\App\Models\Setting::getValue('phone')) }}">{{ \App\Models\Setting::getValue('phone') }}</a><a href="tel:{{ preg_replace('/\s/','',\App\Models\Setting::getValue('phone_secondary')) }}">{{ \App\Models\Setting::getValue('phone_secondary') }}</a><div class="socials">@foreach(['facebook'=>'Facebook','instagram'=>'Instagram','youtube'=>'YouTube'] as $key=>$label)@if($social=\App\Models\Setting::getValue($key.'_url'))<a href="{{ $social }}" target="_blank" rel="noopener">{{ $label }}</a>@endif @endforeach</div></div></div>
<p class="footer-disclaimer">{{ config('eduworld.disclaimer') }}</p>
<div class="footer-bottom"><span>© {{ date('Y') }} {{ $company }}. All rights reserved.</span><div>@foreach(['privacy-policy'=>'Privacy','terms'=>'Terms','payment-terms'=>'Payment terms','refund-policy'=>'Refund policy','disclaimer'=>'Disclaimer'] as $name=>$label)<a href="{{ route($name) }}">{{ $label }}</a>@endforeach</div></div>
</div></footer>
<a class="floating-chat" href="{{ \App\Models\Setting::whatsapp() }}" target="_blank" rel="noopener" aria-label="Chat with Edu World on WhatsApp"><x-icon name="chat"/></a>
@stack('scripts')
</body></html>
