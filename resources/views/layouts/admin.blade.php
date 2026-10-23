<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>@yield('title','Administration') | Edu World</title><link rel="icon" href="{{ asset('images/favicon.svg') }}">@vite(['resources/css/app.css','resources/js/app.js']) @stack('head')</head>
<body class="admin-body"><a class="skip-link" href="#main">Skip to content</a><aside class="admin-sidebar"><a href="{{ route('admin.dashboard') }}"><img src="{{ asset('images/logo-inverse.svg') }}" width="135" height="73" alt="Edu World"></a><span class="eyebrow">TEAM WORKSPACE</span><nav aria-label="Administration">
<a href="{{ route('admin.dashboard') }}" @class(['active'=>request()->routeIs('admin.dashboard')])>Overview</a>
@can('manage-applications')<a href="{{ route('admin.applications.index') }}" @class(['active'=>request()->routeIs('admin.applications.*')])>Applications</a>@endcan
@can('manage-enquiries')<a href="{{ route('admin.enquiries.index') }}" @class(['active'=>request()->routeIs('admin.enquiries.*')])>Enquiries</a>@endcan
@can('manage-payments')<a href="{{ route('admin.payments.index') }}" @class(['active'=>request()->routeIs('admin.payments.*')])>Payments</a>@endcan
@can('manage-content')
<a href="{{ route('admin.posts.index') }}" @class(['active'=>request()->routeIs('admin.posts.*')])>News & gallery</a><a href="{{ route('admin.categories.index') }}" @class(['active'=>request()->routeIs('admin.categories.*')])>Categories</a><a href="{{ route('admin.pages.index') }}" @class(['active'=>request()->routeIs('admin.pages.*')])>Pages & SEO</a>
@endcan
@can('manage-settings')<a href="{{ route('admin.settings.edit') }}" @class(['active'=>request()->routeIs('admin.settings.*')])>Website settings</a><a href="{{ route('admin.users.index') }}" @class(['active'=>request()->routeIs('admin.users.*')])>Team access</a><a href="{{ route('admin.audit') }}" @class(['active'=>request()->routeIs('admin.audit')])>Activity log</a>@endcan
</nav><a class="sidebar-site" href="{{ route('home') }}">View website ↗</a></aside>
<div class="admin-main"><header class="admin-top"><div><strong>{{ auth()->user()->name }}</strong><span>{{ ucwords(str_replace('_',' ',auth()->user()->role)) }}</span></div><form method="post" action="{{ route('logout') }}">@csrf<button class="button-quiet">Sign out</button></form></header>
<main id="main" class="admin-content">
@if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><strong>Please check these details:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main></div>@stack('scripts')</body></html>
