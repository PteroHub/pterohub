@extends('pterohub.client.layout')
@section('title','Account security')
@section('content')
<header class="ph-client-heading"><span class="ph-eyebrow">ACCOUNT</span><h1>Account security</h1><p>Update your password to keep your client account protected.</p></header>
<section class="ph-client-card ph-client-auth ph-security-card">
<h2>Change password</h2>
<p>Enter your current password and choose a new one (12 or more characters, upper and lower case, and a number). You will remain signed in on this device.</p>
<form method="POST" action="{{ route('ph.client.security.password') }}" autocomplete="off">@csrf
<label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
<label>New password<input type="password" name="password" autocomplete="new-password" minlength="12" required></label>
<label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>
<button type="submit">Update password</button>
</form>
</section>
<div class="ph-client-notice">For your security, never share passwords or account access details in support tickets.</div>
@endsection
