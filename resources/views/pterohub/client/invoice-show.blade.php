@extends('pterohub.client.layout')
@section('title','Invoice details')
@section('content')
<div class="ph-client-notice">TEST DOCUMENT — non-fiscal billing simulation. Paid means simulated payment only. No real money has been collected.</div>
<nav class="ph-store-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('ph.client.dashboard') }}">Client area</a><span>/</span><a href="{{ route('ph.client.invoices') }}">Invoices</a><span>/</span>{{ $invoice->number ?: 'Invoice #'.$invoice->id }}</nav>
<header class="ph-client-heading ph-service-heading"><div><span class="ph-eyebrow">BILLING</span><h1>{{ $invoice->number ?: 'Invoice #'.$invoice->id }}</h1><p>Issued {{ $invoice->issued_on ? \Illuminate\Support\Carbon::parse($invoice->issued_on)->format('M j, Y') : 'Not yet issued' }}</p></div><span class="ph-dash-status">{{ ucfirst($invoice->status) }}</span></header>
<div class="ph-service-grid">
  <section class="ph-dash-panel"><span class="ph-eyebrow">INVOICE DETAILS</span><h2>Summary</h2><dl class="ph-service-details">
    <div><dt>Status</dt><dd>{{ ucfirst($invoice->status) }}</dd></div>
    <div><dt>Issue date</dt><dd>{{ $invoice->issued_on ?: 'Not issued' }}</dd></div>
    <div><dt>Due date</dt><dd>{{ $invoice->due_on ?: 'Not scheduled' }}</dd></div>
    <div><dt>Currency</dt><dd>{{ strtoupper($invoice->currency) }}</dd></div>
    @if($invoice->order_id && \Illuminate\Support\Facades\DB::table('orders')->where('id',$invoice->order_id)->where('user_id',auth()->id())->exists())<div><dt>Related order</dt><dd><a href="{{ route('ph.client.orders.show',$invoice->order_id) }}">Order #{{ $invoice->order_id }}</a></dd></div>@endif
  </dl></section>
  <section class="ph-dash-panel"><span class="ph-eyebrow">AMOUNTS</span><h2>Totals</h2><dl class="ph-service-details">
    <div><dt>Subtotal</dt><dd>{{ strtoupper($invoice->currency) }} {{ number_format($invoice->subtotal_minor/100,2) }}</dd></div>
    <div><dt>Tax</dt><dd>Not calculated (test)</dd></div>
    <div><dt>Total</dt><dd><strong>{{ strtoupper($invoice->currency) }} {{ number_format($invoice->total_minor/100,2) }}</strong></dd></div>
  </dl></section>
</div>
<section class="ph-dash-panel ph-invoice-items"><span class="ph-eyebrow">LINE ITEMS</span><h2>Invoice items</h2>
<div class="ph-client-table-wrap ph-responsive-detail"><table><thead><tr><th>Description</th><th>Quantity</th><th>Amount</th></tr></thead><tbody>
@forelse($items as $item)<tr><td data-label="Description">{{ $item->description }}</td><td data-label="Quantity">{{ $item->quantity }}</td><td data-label="Amount">{{ strtoupper($invoice->currency) }} {{ number_format($item->total_minor/100,2) }}</td></tr>
@empty<tr class="ph-record-empty-row"><td colspan="3">No line items have been added yet.</td></tr>@endforelse
</tbody></table></div></section>
<div class="ph-client-notice">Online payments and actual tax invoice issuance are not enabled.</div>
<a class="ph-store-back" href="{{ route('ph.client.invoices') }}">All invoices</a>
@endsection
