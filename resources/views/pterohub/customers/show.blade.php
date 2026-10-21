@extends('pterohub.layout')
@section('title','Customer Profile')
@section('content')

<div class="page-heading"><div><div class="eyebrow">CUSTOMERS / PROFILE</div><h1>{{ $customer->name }}</h1><p class="intro">{{ $customer->email }} · Account #{{ $customer->id }}</p></div><a class="button subtle" href="{{ route('pterohub.customers.index') }}"> Customers</a></div>
<div class="panel customer-panel customer-overview"><div class="panel-header"><h2>Account overview</h2></div><div class="customer-stats">@foreach($counts as $label=>$count)<div class="customer-stat"><small>{{ ucfirst($label) }}</small><h2>{{ $count }}</h2></div>@endforeach</div><p class="helper">Counts are informational. These counts are informational. No payment or server operation is triggered by this page.</p></div>
<div class="panel customer-panel ph111-account"><div class="panel-header"><div><h2>Account &amp; Pterodactyl sync</h2><p>PteroHub is authoritative. Updates to an existing linked account require confirmation from the remote API.</p></div></div>
<p class="helper">Sync: <strong>{{ ucfirst($sync->status ?? 'pending') }}</strong> · Remote user: {{ $remoteLink->remote_user_id ?? 'Not linked' }} · Last issue: {{ $sync->error_code ?? 'None' }}</p>
<form method="POST" action="{{ route('pterohub.customers.account.save',$customer->id) }}">@csrf
@php($legacyParts=preg_split('/\s+/u',trim($customer->name),2))
<div class="customer-form-grid">
<div class="customer-field"><label>First name<input class="customer-control" name="first_name" required maxlength="150" value="{{ old('first_name',$customer->first_name ?? ($legacyParts[0] ?? '')) }}"></label></div>
<div class="customer-field"><label>Last name<input class="customer-control" name="last_name" required maxlength="150" value="{{ old('last_name',$customer->last_name ?? ($legacyParts[1] ?? 'Customer')) }}"></label></div>
<div class="customer-field"><label>Username<input class="customer-control" name="username" required minlength="3" maxlength="32" pattern="[A-Za-z0-9._-]+" value="{{ old('username',$customer->username ?? '') }}" autocomplete="off"></label><small class="helper">For existing linked accounts, enter the current Pterodactyl username or a new one. Username is separate from first and last name.</small></div>
<div class="customer-field"><label>Email<input class="customer-control" type="email" name="email" required maxlength="254" value="{{ old('email',$customer->email) }}"></label></div></div>
<div class="customer-form-actions"><button class="button primary" type="submit">Save account &amp; synchronize</button></div></form>
<form method="POST" action="{{ route('pterohub.customers.sync',$customer->id) }}">@csrf<button class="button subtle" type="submit">Check / retry account sync</button></form>
<p class="helper">An existing remote account with the same email is never claimed automatically. Use Manual Provisioning to verify and link it. A remote creation with an uncertain outcome requires manual reconciliation before retrying.</p></div>
<div class="panel customer-panel"><div class="panel-header"><div><h2>Billing profile</h2><p>Used for future billing workflows. No invoices are issued by this form.</p></div></div>
<form class="customer-billing-form" method="POST" action="{{ route('pterohub.customers.billing.save',$customer->id) }}">@csrf
@if($errors->any())<div class="customer-form-alert" role="alert">Please check the highlighted fields.<ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
@php($fields=['legal_name'=>'Legal name','company'=>'Company','tax_id'=>'Tax ID','vat_number'=>'VAT number','address_line1'=>'Address','address_line2'=>'Address line 2','city'=>'City','region'=>'Region','postal_code'=>'Postal code','country'=>'Country (ISO 2)','pec_email'=>'PEC email','recipient_code'=>'Recipient code'])
<div class="customer-form-grid">
<div class="customer-field"><label for="customer_type">Customer type</label><select class="customer-control" id="customer_type" name="customer_type"><option value="individual" @selected(old('customer_type',$profile->customer_type ?? 'individual')==='individual')>Individual</option><option value="business" @selected(old('customer_type',$profile->customer_type ?? 'individual')==='business')>Business</option></select></div>
@foreach($fields as $key=>$label)<div class="customer-field"><label for="{{ $key }}">{{ $label }}</label><input class="customer-control" id="{{ $key }}" name="{{ $key }}" value="{{ old($key,$profile->$key ?? '') }}" maxlength="200"></div>@endforeach
</div><div class="customer-form-actions"><button class="button primary" type="submit">Save billing profile</button></div></form></div>
@endsection
