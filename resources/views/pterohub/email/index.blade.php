@extends('pterohub.layout')
@section('title','Email Settings')
@section('content')
<p class="ph-settings-back"><a href="{{ route('pterohub.settings.index') }}">All settings</a></p>
<div class="ph18 ph19">
 <div class="ph18-head"><div><div class="ph18-kicker">SETTINGS / EMAIL</div><h1>Email & Notifications</h1><p>Configure SMTP centrally. Secrets are encrypted and never displayed.</p></div><span class="ph18-pill">Admin only</span></div>
 @if(session('ok'))<div class="ph18-alert">{{ session('ok') }}</div>@endif
 @if($errors->any())<div class="ph18-alert error">{{ $errors->first() }}</div>@endif
 <p class="ph19-note"><a href="{{ route('pterohub.notifications.index') }}">Manage automatic notifications and delivery history</a></p>
 <div class="ph19-grid">
  <section class="ph18-card"><div class="ph18-cardhead"><h2>Outgoing email</h2><p>Saved settings override .env. A blank password retains the existing one.</p></div>
   <form method="POST" action="{{ route('pterohub.email.update') }}" class="ph19-form">@csrf
    <label>Mail driver<select name="driver" required><option value="smtp" @selected(old('driver',$stored?->driver ?? $mailer)==='smtp')>SMTP</option><option value="log" @selected(old('driver',$stored?->driver ?? $mailer)==='log')>Log (no delivery)</option></select></label>
    <label>SMTP hostname<input name="host" maxlength="255" autocomplete="off" value="{{ old('host',$stored?->host ?? $host) }}" placeholder="smtp.example.com"></label>
    <div class="ph19-pair"><label>Port<input name="port" type="number" min="1" max="65535" value="{{ old('port',$stored?->port ?? $port) }}"></label><label>Connection<select name="scheme"><option value="" @selected(old('scheme',$stored?->scheme ?? '')==='')>Automatic / STARTTLS</option><option value="smtp" @selected(old('scheme',$stored?->scheme ?? '')==='smtp')>SMTP (STARTTLS if supported)</option><option value="smtps" @selected(old('scheme',$stored?->scheme ?? '')==='smtps')>Implicit TLS / SMTPS</option></select></label></div>
    <label>SMTP username<input name="username" maxlength="254" autocomplete="off" value="{{ old('username',$stored?->username ?? config('mail.mailers.smtp.username')) }}"></label>
    <label>SMTP password <small>{{ $stored?->encrypted_password ? 'Saved (leave blank to keep)' : 'Not stored' }}</small><input type="password" name="password" maxlength="4096" autocomplete="new-password" placeholder="Leave blank to keep existing password"></label>
    <label>From email<input type="email" name="from_address" maxlength="254" value="{{ old('from_address',$stored?->from_address ?? $from) }}"></label>
    <label>Reply-To (optional)<input type="email" name="reply_to" maxlength="254" value="{{ old('reply_to',$stored?->reply_to ?? config('mail.reply_to.address')) }}"></label>
    <div class="ph19-note">From name: <strong>{{ $name }}</strong>. Configure it in <a href="{{ route('pterohub.brand.edit') }}">Brand Settings</a>. Existing SMTP passwords are not exposed by this page.</div>
    <div class="ph18-actions"><button class="ph18-btn primary" type="submit">Save email settings</button></div>
   </form>
  </section>
  <section class="ph19-side"><section class="ph18-card"><div class="ph18-cardhead"><h2>Send test email</h2><p>A single direct SMTP test, not queued.</p></div><form method="POST" action="{{ route('pterohub.email.test') }}" class="ph19-form">@csrf<label>Recipient email<input type="email" name="recipient" required maxlength="254" value="{{ old('recipient',auth()->user()->email) }}"></label><div class="ph18-actions"><button type="submit" class="ph18-btn primary">Send test message</button></div></form></section>
  <section class="ph18-card"><div class="ph18-cardhead"><h2>Notification status</h2></div><div class="ph18-detail"><span>Active driver</span><strong>{{ $mailer }}</strong><span>Queue</span><strong>{{ $queue }}</strong><span>Ticket notifications</span><strong>{{ $ticketEmails ? 'Enabled' : 'Disabled' }}</strong></div><p class="ph19-note">Queued emails need a running worker. Restart long-lived workers after changing SMTP settings. Keep ticket notifications disabled until the test succeeds.</p></section></section>
 </div>
</div>
@endsection
