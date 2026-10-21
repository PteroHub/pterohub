@extends('pterohub.client.layout')
@section('title','Billing profile')
@section('content')
<header class="ph-client-heading"><span class="ph-eyebrow">ACCOUNT</span><h1>Account &amp; billing profile</h1><p>Manage your account and save your billing details for future invoices. Changes are saved to your account, but do not alter already issued invoices.</p></header>
<section class="ph-client-card ph-client-auth ph-billing-form ph111-account">
<h2>Account details</h2>
<p>Your PteroHub account is the source of your name and email. Pterodactyl is updated automatically when synchronization succeeds. Passwords remain separate.</p>
<form method="POST" action="{{ route('ph.client.account.name') }}">@csrf
@php($legacyParts=preg_split('/\s+/u',trim(auth()->user()->name),2))
<label>First name<input type="text" name="first_name" maxlength="150" required value="{{ old('first_name',auth()->user()->first_name ?? ($legacyParts[0] ?? '')) }}"></label>
<label>Last name<input type="text" name="last_name" maxlength="150" required value="{{ old('last_name',auth()->user()->last_name ?? ($legacyParts[1] ?? 'Customer')) }}"></label>
<label>Username<input type="text" name="username" minlength="3" maxlength="32" pattern="[A-Za-z0-9._-]+" required autocomplete="username" value="{{ old('username',auth()->user()->username ?? '') }}"></label>
<p class="ph-client-notice">Your username is separate from your name. Existing customers should enter their current Pterodactyl username or a new one.</p>
@foreach(['first_name','last_name','username'] as $field)@error($field)<p class="ph-field-error">{{ $message }}</p>@enderror @endforeach
<button type="submit">Save identity &amp; synchronize</button></form>
<form method="POST" action="{{ route('ph.client.account.email.request') }}">@csrf
<label>Email address<input type="email" name="email" maxlength="254" required value="{{ old('email',auth()->user()->email) }}"></label>
@error('email')<p class="ph-field-error">{{ $message }}</p>@enderror
<p class="ph-client-notice">Changing email requires confirmation through the new inbox before either account is updated.</p>
<button type="submit">Request email change</button></form>
@php($sync=\Illuminate\Support\Facades\DB::table('pterohub_account_sync')->where('user_id',auth()->id())->first())
<p class="ph-client-notice">Pterodactyl sync: <strong>{{ ucfirst($sync->status ?? 'pending') }}</strong>. If synchronization requires review, contact support.</p>
</section>
<section class="ph-client-card ph-client-auth ph-billing-form">
<form method="POST" data-ph-billing-form action="{{ route('ph.client.profile.save') }}">@csrf
<label>Customer type<select name="customer_type" data-ph-customer-type required><option value="individual" @selected(old('customer_type',$profile->customer_type ?? 'individual')==='individual')>Individual</option><option value="business" @selected(old('customer_type',$profile->customer_type ?? '')==='business')>Business</option></select></label>
<p class="ph-client-notice">Individual customers do not need a VAT number, PEC address or company details. Business fields are shown only for business accounts.</p>
@php($fields = ['legal_name'=>['Legal name',180,'text'], 'company'=>['Company',180,'text'], 'tax_id'=>['Tax ID',40,'text'], 'vat_number'=>['VAT number (business, optional in this test)',40,'text'], 'address_line1'=>['Address',200,'text'], 'address_line2'=>['Address line 2',200,'text'], 'city'=>['City',120,'text'], 'region'=>['Region',120,'text'], 'postal_code'=>['Postal code',32,'text'], 'country'=>['Country (ISO 2-letter code)',2,'text'], 'pec_email'=>['PEC email (business, optional)',200,'email'], 'recipient_code'=>['Recipient code',20,'text']])
<div class="ph-billing-fields">
@foreach($fields as $key=>$field)
<label @if(in_array($key,['company','vat_number','pec_email','recipient_code'],true)) data-ph-business-field @endif>{{ $field[0] }} @if($key==='legal_name')<span class="ph-required">Required</span>@endif
<input type="{{ $field[2] }}" name="{{ $key }}" value="{{ old($key,$profile->$key ?? '') }}" maxlength="{{ $field[1] }}" @if($key==='legal_name') required @endif @if($key==='country') pattern="[A-Za-z]{2}" autocapitalize="characters" placeholder="IT" @endif autocomplete="off"></label>
@error($key)<p class="ph-field-error">{{ $message }}</p>@enderror
@endforeach
</div>
<button type="submit">Save billing profile</button>
</form>
@if($profile)<p class="ph-profile-saved">Saved on {{ \Illuminate\Support\Carbon::parse($profile->updated_at)->format('M j, Y, H:i') }}. You can edit these details at any time.</p>@endif
</section>
@endsection
