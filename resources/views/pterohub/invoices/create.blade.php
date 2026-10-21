@extends('pterohub.layout')
@section('title','New invoice draft')
@section('content')
<div class="ph-invoices">
 <div class="ph-page-head"><div><div class="ph-eyebrow">COMMERCE / BILLING</div><h1>Create invoice draft</h1><p>Copy an existing order into a non-fiscal draft document.</p></div><a class="ph-button ph-soft" href="{{ route('pterohub.invoices.index') }}"> Invoices</a></div>
 <div class="ph-panel ph-form-panel"><div class="ph-panel-head"><div><h2>Document details</h2><p>Amounts are taken from the selected order. Tax calculation is not yet supported.</p></div><span class="ph-status">Draft only</span></div>
 @if($errors->any())<div class="ph-error">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
 <form method="POST" action="{{ route('pterohub.invoices.store') }}">@csrf
 <div class="ph-field"><label for="order_id">Source order <span>*</span></label><select id="order_id" name="order_id" required><option value="">Select a confirmed order</option>@foreach($orders as $order)<option value="{{ $order->id }}" @selected(old('order_id')==$order->id)>{{ $order->reference }} — {{ $order->customer_name }} — € {{ number_format($order->total_minor/100,2,',','.') }}</option>@endforeach</select><p class="ph-help">Only confirmed EUR orders without a linked document appear here.</p></div>
 <div class="ph-form-footer"><p>No invoice number, issue date, tax calculation, charge or server activation.</p><button class="ph-button ph-primary" type="submit" @disabled($orders->isEmpty())>Create draft</button></div>
 </form></div>
</div>
@endsection
