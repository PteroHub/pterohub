@extends('pterohub.layout')
@section('title','Invoices')
@section('content')
<div class="ph-invoices">
 <div class="ph-page-head"><div><div class="ph-eyebrow">COMMERCE / BILLING</div><h1>Invoices</h1><p>Non-fiscal billing simulation. No real invoices or payments are processed.</p></div><a class="ph-button ph-primary" href="{{ route('pterohub.invoices.create') }}"> New draft</a></div>
 @if(session('ok'))<div class="ph-notice">{{ session('ok') }}</div>@endif
 <div class="ph-panel"><form method="GET" action="{{ route('pterohub.invoices.index') }}" class="ph-billing-filter"><label>Status<select name="status"><option value="">All statuses</option>@foreach(['draft','issued','paid','cancelled'] as $choice)<option value="{{ $choice }}" @selected($status === $choice)>{{ ucfirst($choice) }}</option>@endforeach</select></label><label>Customer email, document or ID<input name="q" value="{{ $search }}" maxlength="100" placeholder="Search"></label><button class="ph-button ph-primary" type="submit">Filter</button><a class="ph-button ph-soft" href="{{ route('pterohub.billing.index') }}">Billing overview</a></form><div class="ph-panel-head"><h2>Invoice directory</h2><p>Simulated status changes and test references only.</p></div>
 <div class="ph-table-wrap"><table><thead><tr><th>Document</th><th>Customer</th><th>Status</th><th>Total</th><th>Created</th><th></th></tr></thead><tbody>
 @forelse($invoices as $invoice)
 <tr><td><strong>{{ $invoice->number ?: 'Draft #'.$invoice->id }}</strong><small>Order #{{ $invoice->order_id ?? '—' }}</small></td><td>{{ $invoice->customer_name }}<small>{{ $invoice->customer_email }}</small></td><td><span class="ph-status">{{ ucfirst($invoice->status) }}</span></td><td>€ {{ number_format($invoice->total_minor/100,2,',','.') }}</td><td>{{ $invoice->created_at }}</td><td><a class="ph-button ph-soft" href="{{ route('pterohub.invoices.show',$invoice->id) }}">View </a></td></tr>
 @empty<tr><td colspan="6">No invoice records yet.</td></tr>@endforelse
 </tbody></table></div><div class="ph-pagination">{{ $invoices->links() }}</div></div>
</div>
@endsection
