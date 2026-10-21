@extends('pterohub.client.layout')
@section('title','Order details')
@section('content')
<div class="ph-store-breadcrumb"><a href="{{ route('ph.client.dashboard') }}">Client area</a><span>/</span><a href="{{ route('ph.client.orders') }}">Orders</a><span>/</span>{{ $order->reference }}</div>
<header class="ph-client-heading"><span class="ph-eyebrow">ORDER DETAILS</span><h1>{{ $order->reference }}</h1><p>Created {{ \Illuminate\Support\Carbon::parse($order->created_at)->format('M j, Y') }} · {{ ucfirst($order->status) }}</p></header>
<section class="ph-client-card"><h2>Configurations</h2><div class="ph-client-table-wrap ph-responsive-detail"><table><thead><tr><th>Hosting plan</th><th>Billing period</th><th>Amount</th></tr></thead><tbody>
@foreach($items as $item)
<tr><td data-label="Hosting plan">{{ $item->plan_name ?? 'Hosting plan #'.$item->hosting_plan_id }}</td><td data-label="Billing period">{{ $item->billing_months }} {{ \Illuminate\Support\Str::plural('month',$item->billing_months) }}</td><td data-label="Amount">€{{ number_format($item->amount_minor/100,2) }}</td></tr>
@endforeach
</tbody></table></div><div class="ph-cart-summary-line ph-cart-grand-total"><span>Total</span><strong>€{{ number_format($order->total_minor/100,2) }}</strong></div></section>
<section class="ph-client-card ph-order-next"><h2>Order status and next steps</h2>
@if($order->status === 'draft')
<p>This configuration has been saved as a draft. It has not been submitted for payment or provisioning. You can review the items above or return to the hosting catalog to create a new draft.</p>
<p>Only sandbox payments are available. Sandbox verification does not confirm the order, issue a tax invoice or activate a server.</p>
@if(config('pterohub_payments.enabled'))
<div class="ph-sandbox-checkout">
@if(app(\App\Services\PterohubSandboxPayments::class)->enabled('stripe'))
<form method="POST" action="{{ route('ph.sandbox.start',$order->id) }}">@csrf<input type="hidden" name="provider" value="stripe"><button type="submit" class="ph-button-secondary">Stripe test checkout</button></form>
@endif
@if(app(\App\Services\PterohubSandboxPayments::class)->enabled('paypal'))
<form method="POST" action="{{ route('ph.sandbox.start',$order->id) }}">@csrf<input type="hidden" name="provider" value="paypal"><button type="submit" class="ph-button-secondary">PayPal sandbox checkout</button></form>
@endif
</div>
@endif
@php($sandboxAttempts=\Illuminate\Support\Facades\DB::table('pterohub_sandbox_payment_attempts')->where('order_id',$order->id)->where('user_id',auth()->id())->orderByDesc('id')->limit(10)->get())
@if($sandboxAttempts->isNotEmpty())
<h3>Sandbox payment checks</h3><div class="ph-client-table-wrap"><table><thead><tr><th>Provider</th><th>State</th><th>Amount</th></tr></thead><tbody>
@foreach($sandboxAttempts as $a)<tr><td>{{ ucfirst($a->provider) }}</td><td>{{ ucfirst($a->status) }} @if($a->provider==='paypal' && $a->status==='pending')
<form method="POST" action="{{ route('ph.sandbox.paypal.capture',$a->id) }}">@csrf<button type="submit" class="ph-button-secondary">Confirm sandbox capture</button></form>
@endif</td><td>€{{ number_format($a->amount_minor/100,2) }}</td></tr>@endforeach
</tbody></table></div>
@endif
<form method="POST" action="{{ route('ph.client.orders.cancel',$order->id) }}" onsubmit="return confirm('Cancel this draft order? This cannot be undone.');">@csrf<button type="submit" class="ph-button-secondary">Cancel draft order</button></form>
@elseif($order->status === 'confirmed')
<p>Your order is confirmed for internal review. No payment has been collected and no hosting server has been created. A draft billing preview may be prepared by the administrator.</p>
@elseif($order->status === 'cancelled' || $order->status === 'canceled')
<p>This order was cancelled. It remains visible in your order history and no payment or server was created by the draft workflow.</p>
@else
<p>Current status: {{ ucfirst($order->status) }}. No payment or provisioning actions are available from this preview.</p>
@endif
</section>
<div class="ph-client-notice">RC sandbox only: no live charges and no automatic server provisioning.</div>
<a class="ph-store-back" href="{{ route('ph.client.orders') }}">All orders</a>
@endsection
