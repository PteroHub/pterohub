@extends('pterohub.client.layout')
@section('title','Client dashboard')
@section('content')
<section class="ph-dash-intro">
  <div><span class="ph-eyebrow">CLIENT WORKSPACE</span><h1>Welcome back, {{ auth()->user()->name }}</h1><p>Everything about your hosting account, all in one place.</p></div>
  <a class="ph-client-button" href="{{ route('ph.client.getting-started') }}">Getting started</a>
</section>
@if(session('ph_cart_v1'))
<div class="ph-dash-cart-banner"><div><span class="ph-eyebrow">SHOPPING CART</span><strong>Your configurations are waiting</strong><p>Review your selections before creating a draft order.</p></div><a class="ph-client-button" href="{{ route('ph.cart.show') }}">Review cart</a></div>
@endif
@if(($counts['services'] ?? 0) === 0 && ($counts['orders'] ?? 0) === 0)
<section class="ph-dash-start" aria-labelledby="ph-dash-start-title">
  <div><span class="ph-eyebrow">GETTING STARTED</span><h2 id="ph-dash-start-title">Your hosting workspace is ready</h2><p>Browse the available games and plans. You can save configurations in your cart before creating a draft order.</p></div>
  <a class="ph-client-button" href="{{ route('ph.client.catalog') }}">Browse available plans</a>
</section>
@endif
<section class="ph-dash-overview" aria-label="Account overview">
  <div><span class="ph-eyebrow">ACCOUNT STATUS</span><strong>{{ $counts['services'] > 0 ? 'Your hosting workspace' : 'Ready when you are' }}</strong><p>{{ $counts['services'] > 0 ? 'Review your services and their next renewal dates.' : 'Choose a game and plan to start your hosting journey.' }}</p></div>
  <div class="ph-dash-overview-actions"><a href="{{ route('ph.client.services') }}">Manage services</a><a href="{{ route('ph.client.tickets.create') }}">Get support</a></div>
</section>
<div class="ph-dash-stats">
@foreach(['services'=>'My services','orders'=>'Orders','invoices'=>'Invoices','tickets'=>'Support tickets'] as $key=>$label)
  <a class="ph-dash-stat" href="{{ route('ph.client.'.$key) }}"><span>{{ $label }}</span><strong>{{ $counts[$key] }}</strong><small>View {{ strtolower($label) }}</small></a>
@endforeach
</div>
<section class="ph-account-highlights" aria-label="Account highlights">
  <div class="ph-account-highlight"><span>Open support tickets</span><strong>{{ $openTickets }}</strong><a href="{{ route('ph.client.tickets') }}">Review support requests</a></div>
  <div class="ph-account-highlight"><span>Invoices awaiting settlement</span><strong>{{ $pendingInvoices }}</strong><a href="{{ route('ph.client.invoices') }}">Review invoices</a></div>
  <div class="ph-account-highlight"><span>Services due within 30 days</span><strong>{{ $dueSoonServices }}</strong><a href="{{ route('ph.client.services') }}">Review services</a></div>
</section>
<div class="ph-dash-columns">
  <section class="ph-dash-panel"><div class="ph-dash-panel-head"><div><span class="ph-eyebrow">HOSTING</span><h2>My services</h2></div><a href="{{ route('ph.client.services') }}">View all</a></div>
    @forelse($recentServices as $service)
      <a class="ph-dash-list-row" href="{{ route('ph.client.services.show', $service->id) }}"><div><strong>{{ $service->plan_name }}</strong><small>Service #{{ $service->id }}@if($service->next_due_at) · Next due {{ \Illuminate\Support\Carbon::parse($service->next_due_at)->format('M j, Y') }}@endif</small></div><span class="ph-dash-status">{{ ucfirst($service->status) }}</span></a>
    @empty <div class="ph-dash-empty"><strong>No active hosting services yet</strong><p>Explore the catalog to see available game server plans.</p><a href="{{ route('ph.client.catalog') }}">Browse game hosting</a></div>
    @endforelse
  </section>
  <section class="ph-dash-panel"><div class="ph-dash-panel-head"><div><span class="ph-eyebrow">BILLING</span><h2>Recent orders</h2></div><a href="{{ route('ph.client.orders') }}">View all</a></div>
    @forelse($recentOrders as $order)
      <a class="ph-dash-list-row" href="{{ route('ph.client.orders.show',$order->id) }}"><div><strong>{{ $order->reference }}</strong><small>€{{ number_format($order->total_minor/100,2) }} · {{ \Illuminate\Support\Carbon::parse($order->created_at)->format('M j, Y') }}</small></div><span class="ph-dash-status">{{ ucfirst($order->status) }}</span></a>
    @empty <div class="ph-dash-empty"><strong>No orders yet</strong><p>Your orders will appear here when you create one.</p></div>
    @endforelse
  </section>
  <section class="ph-dash-panel"><div class="ph-dash-panel-head"><div><span class="ph-eyebrow">HELP CENTER</span><h2>Recent tickets</h2></div><a href="{{ route('ph.client.tickets') }}">View all</a></div>
    @forelse($recentTickets as $ticket)
      <a class="ph-dash-list-row" href="{{ route('ph.client.tickets.show',$ticket->id) }}"><div><strong>{{ $ticket->subject }}</strong><small>Ticket #{{ $ticket->id }}</small></div><span class="ph-dash-status">{{ ucfirst($ticket->status) }}</span></a>
    @empty <div class="ph-dash-empty"><strong>No support requests</strong><p>Need help? Our support area is ready for your questions.</p><a href="{{ route('ph.client.tickets.create') }}">Open a support ticket</a></div>
    @endforelse
  </section>
  <section class="ph-dash-panel"><div class="ph-dash-panel-head"><div><span class="ph-eyebrow">BILLING</span><h2>Recent invoices</h2></div><a href="{{ route('ph.client.invoices') }}">View all</a></div>
    @forelse($recentInvoices as $invoice)
      <a class="ph-dash-list-row" href="{{ route('ph.client.invoices.show',$invoice->id) }}"><div><strong>{{ $invoice->number ?: 'Invoice #'.$invoice->id }}</strong><small>{{ strtoupper($invoice->currency) }} {{ number_format($invoice->total_minor/100,2) }}@if($invoice->due_on) · Due {{ \Illuminate\Support\Carbon::parse($invoice->due_on)->format('M j, Y') }}@endif</small></div><span class="ph-dash-status">{{ ucfirst($invoice->status) }}</span></a>
    @empty <div class="ph-dash-empty"><strong>No invoices yet</strong><p>Invoices will appear here when issued to your account.</p></div>
    @endforelse
  </section>
  <section class="ph-dash-panel ph-dash-help"><span class="ph-eyebrow">ACCOUNT</span><h2>Quick actions</h2><p>Keep your account details up to date and find the tools you need.</p><div class="ph-dash-actions"><a href="{{ route('ph.client.profile') }}">Update billing profile</a><a href="{{ route('ph.client.security') }}">Account security</a><a href="{{ route('ph.client.tickets.create') }}">Create support ticket</a><a href="{{ route('ph.client.catalog') }}">Explore game hosting</a><a href="{{ route('ph.client.getting-started') }}">Getting started guide</a></div></section>
</div>
<div class="ph-client-notice">Development preview: payments and automatic server provisioning are not enabled.</div>
@endsection
