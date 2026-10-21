@extends('pterohub.client.layout')
@section('title','New ticket')
@section('content')<section class="ph-client-card ph-client-auth"><h1>New support ticket</h1>
<form method="POST" action="{{ route('ph.client.tickets.store') }}">@csrf
<label>Subject<input name="subject" value="{{ old('subject') }}" required maxlength="180"></label>
<label>Message<textarea name="body" required rows="7" maxlength="15000">{{ old('body') }}</textarea></label>
<button type="submit">Send ticket</button></form></section>@endsection
