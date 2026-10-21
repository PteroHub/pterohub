@extends('pterohub.client.layout')
@section('title','Client Sign In')
@section('content')
<section class="ph-client-card ph-client-auth"><h1>Sign in</h1><p>Access your hosting account.</p>
<form method="POST" action="{{ route('ph.client.login.submit') }}">@csrf
<label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
<label>Password<input type="password" name="password" autocomplete="current-password" required></label>
@if(config('pterohub_client.turnstile_site_key'))
<div class="cf-turnstile" data-sitekey="{{ config('pterohub_client.turnstile_site_key') }}"></div>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
<button type="submit">Sign in</button></form>
<p><a href="{{ route('ph.client.password.request') }}">Forgot your password?</a></p>
<p>New customer? <a href="{{ route('ph.client.register') }}">Create an account</a></p></section>
@endsection
