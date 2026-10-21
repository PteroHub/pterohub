@extends('pterohub.client.layout')
@section('title','Register')
@section('content')
<section class="ph-client-card ph-client-auth"><h1>Create your account</h1>
@if(!config('pterohub_client.registration_enabled'))<p>Registration is not enabled yet.</p>
@else
<form method="POST" action="{{ route('ph.client.register.submit') }}">@csrf
<label>First name<input name="first_name" value="{{ old('first_name') }}" required maxlength="150" autocomplete="given-name"></label>
<label>Last name<input name="last_name" value="{{ old('last_name') }}" required maxlength="150" autocomplete="family-name"></label>
<label>Username<input name="username" value="{{ old('username') }}" required minlength="3" maxlength="32" pattern="[A-Za-z0-9._-]+" autocomplete="username"></label>
<label>Email<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label>
<label>Password<input type="password" name="password" required minlength="12" autocomplete="new-password"></label>
<label>Confirm password<input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"></label>
@if(config('pterohub_client.turnstile_site_key'))
<div class="cf-turnstile" data-sitekey="{{ config('pterohub_client.turnstile_site_key') }}"></div>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@else<p>Registration is unavailable until anti-bot protection is configured.</p>@endif
<button type="submit" @disabled(!config('pterohub_client.turnstile_site_key'))>Register</button></form>
@endif
</section>@endsection
