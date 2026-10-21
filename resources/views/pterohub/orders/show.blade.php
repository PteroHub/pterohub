@extends('pterohub.layout')
@section('title','Order')
@section('content')
<div class="orders-ui"><div class="page-heading"><div><div class="eyebrow">COMMERCE / ORDER DETAILS</div><h1>{{ $order->reference }}</h1><p class="intro">Customer: {{ $order->customer_name }} · {{ $order->customer_email }}</p></div><a class="button subtle" href="{{ route('pterohub.orders.index') }}"> Orders</a></div>
@if(session('ok'))<div class="notice success">{{ session('ok') }}</div>@endif
<div class="panel"><div class="panel-header"><h2>Order summary</h2><span class="status-pill">{{ $order->status }}</span></div><p>Currency: {{ $order->currency }} · Total: <strong>€ {{ number_format($order->total_minor / 100,2,',','.') }}</strong></p>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Product</th><th>Billing period</th><th>Amount</th></tr></thead><tbody>@foreach($items as $item)<tr><td>{{ $item->plan_name }}</td><td>{{ $item->billing_months }} month(s)</td><td>€ {{ number_format($item->amount_minor / 100,2,',','.') }}</td></tr>@endforeach</tbody></table></div>
@if($order->status === 'draft')
<div class="ph-canary-action"><h3>Internal order confirmation</h3><p>Confirms the order for administrative review only. This does not charge the customer, issue a fiscal invoice or create a server. This action cannot be repeated.</p>
<form method="POST" action="{{ route('pterohub.orders.confirm',$order->id) }}" onsubmit="return confirm('Confirm this draft order for internal review? No payment or server will be created.');">@csrf<button class="button primary" type="submit">Confirm order</button></form></div>
@elseif($order->status === 'confirmed')
<div class="ph-canary-action"><h3>Confirmed for internal review</h3><p>Payment and provisioning are still disabled. A non-fiscal draft document may be prepared for review.</p>
@if($invoice)<a class="button subtle" href="{{ route('pterohub.invoices.show',$invoice->id) }}">View draft document #{{ $invoice->id }}</a>
@else<a class="button primary" href="{{ route('pterohub.invoices.create') }}">Create draft document</a>@endif</div>
@elseif($invoice)<p><a href="{{ route('pterohub.invoices.show',$invoice->id) }}">Linked draft document #{{ $invoice->id }}</a></p>@endif
<div class="ph-canary-action"><h3>Order activity</h3>
@if($events->isEmpty())<p>No administrative events recorded for this order yet.</p>
@else<ul class="ph-canary-events">@foreach($events as $event)<li><strong>{{ str_replace('_',' ',ucfirst($event->action)) }}</strong> · {{ $event->created_at }} · {{ $event->actor_name ?? 'System' }}</li>@endforeach</ul>@endif</div>
<p class="helper">Canary preview: no payments, issued tax invoices, or server provisioning.</p></div>
</div>
@endsection
