@extends('pterohub.client.layout')
@section('title','Verify email')
@section('content')<section class="ph-client-card ph-client-auth"><h1>Verify your email</h1>
<p>We sent a verification link to your email address. Verify your email before accessing the client area.</p>
<form method="POST" action="{{ route('verification.send') }}">@csrf<button type="submit">Resend verification email</button></form></section>@endsection
