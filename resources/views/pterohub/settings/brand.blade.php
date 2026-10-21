@extends('pterohub.layout')
@section('title','Brand Settings')
@section('content')
<p class="ph-settings-back"><a href="{{ route('pterohub.settings.index') }}">All settings</a></p>
<div class="ph18"><div class="ph18-head"><div><div class="ph18-kicker">SETTINGS / WHITE LABEL</div><h1>Company & Email Branding</h1><p>Make this PteroHub installation yours. Email transport credentials remain in the server configuration.</p></div></div>
@if(session('ok'))<div class="ph18-alert">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="ph18-alert error">{{ $errors->first() }}</div>@endif
<section class="ph18-card" style="max-width:800px"><div class="ph18-cardhead"><h2>Brand identity</h2><p>Leave a field blank to inherit the application/company fallback.</p></div>
<form method="POST" action="{{ route('pterohub.brand.update') }}" class="ph18-form">@csrf
<label class="ph18-field">Company name<input name="company_name" maxlength="120" value="{{ old('company_name',$brand->company_name??'') }}" placeholder="{{ $fallbackName }}"></label>
<label class="ph18-field">Email sender name<input name="sender_name" maxlength="120" value="{{ old('sender_name',$brand->sender_name??'') }}" placeholder="Use company name / APP_NAME"></label>
<label class="ph18-field">Reply-to email (reserved for email templates)<input name="reply_to_email" type="email" maxlength="254" value="{{ old('reply_to_email',$brand->reply_to_email??'') }}" placeholder="support@example.com"></label>
<p class="ph18-help">Sender address: <strong>{{ $fromAddress }}</strong> (configured via MAIL_FROM_ADDRESS in .env). Existing SMTP credentials are not changed. Reply-to is stored for future notification templates but not yet applied to all messages.</p>
<div class="ph18-actions"><button class="ph18-btn primary" type="submit">Save branding</button></div>
</form></section></div>
@endsection
