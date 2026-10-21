@extends('pterohub.client.layout')
@section('title','Reset password')
@section('content')<section class="ph-client-card ph-client-auth"><h1>Reset password</h1>
<p>Enter your customer account email to request a one-time reset link. The link expires after 60 minutes. Email delivery must be configured by the administrator.</p>
<form method="POST" action="{{ route('ph.client.password.email') }}">@csrf
<label>Email<input type="email" name="email" required autocomplete="email"></label><button type="submit">Send reset link</button></form></section>@endsection
