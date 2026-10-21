@extends('pterohub.layout')
@section('title','Settings')
@section('content')
<div class="ph-settings">
  <div class="ph-settings-head"><div class="eyebrow">SYSTEM / SETTINGS</div><h1>Settings</h1><p>Manage platform configuration from one place. Sensitive application internals remain in the server environment.</p></div>
  <div class="ph-settings-grid">
    @foreach ([
      ['General & Branding', 'Currency, timezone, support email and brand identity.', route('pterohub.settings.group','general')],
      ['Email & Notifications', 'SMTP, sender, test messages and delivery configuration.', route('pterohub.email.index')],
      ['Notification Center', 'Email templates, event preferences and delivery history.', route('pterohub.notifications.index')],
      ['Captcha & Security', 'Cloudflare Turnstile or Google reCAPTCHA credentials.', route('pterohub.settings.group','captcha')],
      ['API & Integrations', 'Google, Cloudflare, Discord and Steam credentials.', route('pterohub.settings.group','integrations')],
      ['Pterodactyl Connection', 'Panel HTTPS URL, Application API key and read-only diagnostics.', route('pterohub.settings.group','pterodactyl')],
      ['Payments', 'Stripe and PayPal configuration, sandbox and webhook credentials.', route('pterohub.settings.group','payments')],
      ['Hosting & Provisioning', 'Hosting defaults and provisioning safeguards.', route('pterohub.settings.group','hosting')],
      ['Customer Portal', 'Client domain and future registration policies.', route('pterohub.settings.group','portal')],
      ['System & Extensions', 'Installed extensions and application capabilities.', route('pterohub.extensions.index')],
    ] as $item)
      <a class="ph-settings-card" href="{{ $item[2] }}"><h2>{{ $item[0] }}</h2><p>{{ $item[1] }}</p><span>Open settings</span></a>
    @endforeach
  </div>
  <div class="ph-settings-note">Changing values here does not modify .env, activate payment collection or enable automatic server provisioning. External integrations require a separate activation and verification step.</div>
</div>
@endsection
