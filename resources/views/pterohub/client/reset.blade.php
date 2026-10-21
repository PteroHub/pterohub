@extends('pterohub.client.layout')
@section('title','Choose new password')
@section('content')<section class="ph-client-card ph-client-auth"><h1>Choose a new password</h1>
<p>Choose a password of at least 12 characters with uppercase and lowercase letters and numbers. Reset links can only be used once.</p>
<form method="POST" action="{{ route('ph.client.password.update') }}">@csrf
<input type="hidden" name="token" value="{{ $token }}">
<label>Email<input type="email" name="email" value="{{ old('email',$email) }}" required></label>
<label>New password<input type="password" name="password" required minlength="12"></label>
<label>Confirm password<input type="password" name="password_confirmation" required minlength="12"></label>
<button type="submit">Update password</button></form></section>@endsection
