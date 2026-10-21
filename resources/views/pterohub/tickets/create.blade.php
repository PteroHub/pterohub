@extends('pterohub.layout')
@section('title','New Ticket')
@section('content')
<div class="ph18"><div class="ph18-head"><div><div class="ph18-kicker">SUPPORT / NEW TICKET</div><h1>Create support ticket</h1><p>Open a conversation for an existing PteroHub customer.</p></div><a class="ph18-btn soft" href="{{ route('pterohub.tickets.index') }}"> Tickets</a></div>
<section class="ph18-card ph18-narrow"><div class="ph18-cardhead"><h2>Ticket information</h2></div>
@if($errors->any())<div class="ph18-alert error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('pterohub.tickets.store') }}" class="ph18-form">@csrf
<label class="ph18-field">Customer<select name="user_id" required><option value="">Select customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('user_id')==$customer->id)>{{ $customer->name }} — {{ $customer->email }}</option>@endforeach</select></label>
<label class="ph18-field">Subject<input name="subject" required maxlength="255" value="{{ old('subject') }}" placeholder="How can we help?"></label>
<div class="ph18-grid"><label class="ph18-field">Department<select name="department"><option value="support">General support</option><option value="technical">Technical</option><option value="billing">Billing</option></select></label><label class="ph18-field">Priority<select name="priority"><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option></select></label></div>
<label class="ph18-field">Message<textarea name="body" rows="7" required maxlength="10000" placeholder="Describe the request...">{{ old('body') }}</textarea></label>
<div class="ph18-actions"><button type="submit" class="ph18-btn primary">Create ticket</button></div></form></section></div>
@endsection
