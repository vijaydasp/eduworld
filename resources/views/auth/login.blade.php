@extends('layouts.app')
@section('title','Team sign in | Edu World')
@section('content')
<section class="section auth-section"><div class="panel auth-panel"><span class="eyebrow">EDU WORLD TEAM</span><h1>Welcome back.</h1><p>Sign in to manage applications and your website.</p><form method="post" action="{{ route('login.store') }}">@csrf
<div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
<div class="field"><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" required></div><button class="button">Sign in <x-icon name="arrow"/></button>
</form><p class="small muted">Need access? Contact your website administrator.</p></div></section>
@endsection
