@extends('pterohub.client.layout')
@section('title', 'Getting started')
@section('content')
<div class="ph-client-heading"><span>CLIENT WORKSPACE</span><h1>Getting started</h1><p>Use this guide to explore your hosting account. Payments and automatic server provisioning are disabled during development.</p></div>
<section class="ph-onboard-grid" aria-label="Account setup steps">
  <article class="ph-onboard-step"><span class="ph-onboard-number">01</span><div><h2>Explore game hosting</h2><p>Browse games and hosting configurations from the live catalog.</p><span class="ph-onboard-state">Available now</span></div><a href="{{ route('ph.client.catalog') }}">Browse plans</a></article>
  <article class="ph-onboard-step"><span class="ph-onboard-number">02</span><div><h2>Review your cart</h2><p>Add a configuration and check the billing period and total before submitting a draft order.</p><span class="ph-onboard-state">No payment required</span></div><a href="{{ route('ph.cart.show') }}">Open cart</a></article>
  <article class="ph-onboard-step"><span class="ph-onboard-number">03</span><div><h2>Check your orders</h2><p>Orders created in this development environment remain drafts.</p><span class="ph-onboard-state">{{ $hasOrders ? 'Order history available' : 'No orders yet' }}</span></div><a href="{{ route('ph.client.orders') }}">My orders</a></article>
  <article class="ph-onboard-step"><span class="ph-onboard-number">04</span><div><h2>Complete billing details</h2><p>Update your customer profile to see how account information is managed.</p><span class="ph-onboard-state">{{ $hasProfile ? 'Details provided' : 'Details not completed' }}</span></div><a href="{{ route('ph.client.profile') }}">Billing profile</a></article>
  <article class="ph-onboard-step"><span class="ph-onboard-number">05</span><div><h2>Review hosting services</h2><p>Services will appear after provisioning is enabled in a later release.</p><span class="ph-onboard-state">{{ $hasServices ? 'Services available' : 'No services yet' }}</span></div><a href="{{ route('ph.client.services') }}">My services</a></article>
  <article class="ph-onboard-step"><span class="ph-onboard-number">06</span><div><h2>Try the support area</h2><p>Create a support ticket and review its status from your client area.</p><span class="ph-onboard-state">{{ $hasTickets ? 'Ticket history available' : 'No tickets yet' }}</span></div><a href="{{ route('ph.client.tickets') }}">Support tickets</a></article>
</section>
<div class="ph-client-notice">This is a development environment. Do not enter real payment information or sensitive personal details when testing.</div>
@endsection
