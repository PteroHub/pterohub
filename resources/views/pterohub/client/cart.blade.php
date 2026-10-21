@extends('pterohub.client.layout')
@section('title','Shopping cart')
@section('description','Review your game server hosting configurations before creating a draft order.')
@section('content')
<div class="ph-store-breadcrumb"><a href="{{ route('ph.home') }}">Home</a><span>/</span><a href="{{ route('ph.client.catalog') }}">Game hosting</a><span>/</span>Cart</div>
<section class="ph-client-heading"><span class="ph-eyebrow">YOUR CONFIGURATIONS</span><h1>Shopping cart</h1><p>Review the configurations and billing periods you selected. Prices are updated from the live catalog.</p></section>
@if(!$rows)
  <div class="ph-client-card ph-cart-empty"><h2>Your cart is empty</h2><p>Choose a game hosting plan to get started.</p><a class="ph-client-button" href="{{ route('ph.client.catalog') }}">Explore hosting plans</a></div>
@else
<div class="ph-cart-layout">
  <section class="ph-cart-items" aria-label="Cart items">
    @foreach($rows as $row)
    <article class="ph-client-card ph-cart-item">
      <div class="ph-cart-item-main">
        <span class="ph-eyebrow">{{ $row['available'] ? $row['plan']->game_name : 'UNAVAILABLE' }}</span>
        <h2>@if($row['available'])<a href="{{ route('ph.client.plan',[$row['plan']->game_slug,$row['plan']->slug]) }}">{{ $row['name'] }}</a>@else{{ $row['name'] }}@endif</h2>
        @if($row['available'])<p>{{ $row['months'] === 1 ? 'Monthly' : $row['months'].' months' }} · €{{ number_format($row['unit_minor']/100,2) }} each (including setup)</p>
        @else<p class="ph-cart-warning">This configuration or its price is no longer available. Remove it to continue.</p>@endif
      </div>
      <div class="ph-cart-item-controls">
        <form method="POST" action="{{ route('ph.cart.update') }}" class="ph-cart-quantity-form">@csrf
          <input type="hidden" name="key" value="{{ $row['key'] }}">
          <label for="qty-{{ $loop->index }}">Quantity</label>
          <select id="qty-{{ $loop->index }}" name="quantity">@for($i=1;$i<=5;$i++)<option value="{{ $i }}" @selected($row['quantity']===$i)>{{ $i }}</option>@endfor</select>
          <button type="submit" class="ph-cart-small-button">Update</button>
        </form>
        <form method="POST" action="{{ route('ph.cart.remove') }}">@csrf<input type="hidden" name="key" value="{{ $row['key'] }}"><button type="submit" class="ph-cart-remove">Remove</button></form>
      </div>
      @if($row['available'])<div class="ph-cart-line-total">€{{ number_format($row['line_minor']/100,2) }}</div>@endif
    </article>
    @endforeach
    <a class="ph-store-back" href="{{ route('ph.client.catalog') }}">Continue shopping</a>
  </section>
  <aside class="ph-client-card ph-cart-summary"><span class="ph-eyebrow">ORDER PREVIEW</span><h2>Summary</h2>
    <div class="ph-cart-summary-line"><span>Configurations</span><strong>{{ array_sum(array_column($rows,'quantity')) }}</strong></div>
    <div class="ph-cart-summary-line ph-cart-grand-total"><span>Estimated total</span><strong>€{{ number_format($total/100,2) }}</strong></div>
    @if(!$available)<p class="ph-cart-warning">Unavailable items must be removed before continuing.</p>@endif
    @auth
      @if(!auth()->user()->is_pterohub_admin && auth()->user()->hasVerifiedEmail())
      <form method="POST" action="{{ route('ph.client.cart.checkout') }}">@csrf<button type="submit" class="ph-client-button ph-cart-checkout" @disabled(!$available)>Create draft order</button></form>
      @elseif(auth()->user()->is_pterohub_admin)
      <p class="ph-client-muted">Administrators cannot create customer orders.</p>
      @else
      <a class="ph-client-button ph-cart-checkout" href="{{ route('verification.notice') }}">Verify your email</a>
      @endif
    @else
      <a class="ph-client-button ph-cart-checkout" href="{{ route('ph.client.login') }}">Sign in to continue</a>
    @endauth
    <p class="ph-client-muted">Development preview: creating an order does not charge you or provision a server. Totals are rechecked on submission.</p>
  </aside>
</div>
@endif
@endsection
