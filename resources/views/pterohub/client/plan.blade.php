@extends('pterohub.client.layout')
@section('title',$item->name.' hosting')
@section('description','Explore hosting specifications and available billing periods for '.$item->name.'.')
@section('content')
<div class="ph-store-breadcrumb"><a href="{{ route('ph.home') }}">Home</a><span>/</span><a href="{{ route('ph.client.catalog',['game'=>$item->game_slug]) }}">{{ $item->game_name }}</a><span>/</span>{{ $item->name }}</div>
<div class="ph-product-layout">
<section class="ph-product-content"><div class="ph-product-heading"><span class="ph-eyebrow">{{ strtoupper($item->game_name) }} HOSTING</span><h1>{{ $item->name }}</h1><p>Hosting resources and billing options for this configuration.</p></div>
<div class="ph-product-banner"><div class="ph-product-emblem" aria-hidden="true">{{ strtoupper(substr($item->game_name,0,2)) }}</div><div><strong>{{ $item->game_name }}</strong><span>{{ $item->game_engine ?: 'Game server hosting' }}</span></div></div>
<div class="ph-client-card"><h2>Included resources</h2><dl class="ph-product-specs"><div><dt>Memory</dt><dd>{{ number_format($item->memory_mb/1024,1) }} GB RAM</dd></div><div><dt>Storage</dt><dd>{{ number_format($item->disk_mb/1024,1) }} GB</dd></div><div><dt>CPU limit</dt><dd>{{ $item->cpu_percent }}%</dd></div><div><dt>Allocations</dt><dd>{{ $item->allocations }}</dd></div><div><dt>Backups</dt><dd>{{ $item->backups }}</dd></div><div><dt>Databases</dt><dd>{{ $item->databases }}</dd></div></dl></div>
@if($item->game_description)<div class="ph-client-card"><h2>About this game</h2><p>{{ $item->game_description }}</p></div>@endif
<section class="ph-client-card ph-product-guidance" aria-labelledby="ph-product-guidance-title">
<h2 id="ph-product-guidance-title">Before you order</h2>
<p>Review the resources and select the billing period that fits your needs. You can update your cart before submitting a draft order.</p>
<p>During development, orders are recorded for review only. No payment is collected and no game server is provisioned.</p>
</section>
</section>
<aside class="ph-product-checkout"><div class="ph-client-card"><span class="ph-eyebrow">CONFIGURATION</span><h2>Choose your billing period</h2>
@if($prices->isNotEmpty())
<form method="POST" action="{{ route('ph.cart.add') }}" data-ph-price-form>@csrf<input type="hidden" name="plan_id" value="{{ $item->id }}">
<fieldset class="ph-product-periods"><legend>Available billing options</legend>@foreach($prices as $price)
<label class="ph-product-period"><input type="radio" name="billing_months" value="{{ $price->billing_months }}" @checked($loop->first) data-price="{{ $price->amount_minor }}" data-setup="{{ $price->setup_minor }}"><span><strong>{{ $price->billing_months === 1 ? 'Monthly' : $price->billing_months.' months' }}</strong><small>@if($price->setup_minor > 0)Includes €{{ number_format($price->setup_minor/100,2) }} setup @elseNo setup fee @endif</small></span><b>€{{ number_format(($price->amount_minor+$price->setup_minor)/100,2) }}</b></label>
@endforeach</fieldset>
<div class="ph-product-total"><span>Price per configuration</span><strong data-ph-price-total>€{{ number_format(($prices->first()->amount_minor+$prices->first()->setup_minor)/100,2) }}</strong></div>
<button type="submit" class="ph-client-button ph-product-submit">Add to cart</button>
<a class="ph-store-back" href="{{ route('ph.cart.show') }}">View cart</a>
</form>
@else<div class="ph-store-empty"><p>No billing prices have been configured for this plan.</p></div>@endif
<p class="ph-client-muted">Development mode: cart and draft orders only. No payment is taken and no server is created.</p>
</div><a class="ph-store-back" href="{{ route('ph.client.catalog',['game'=>$item->game_slug]) }}">Back to {{ $item->game_name }} plans</a></aside>
</div>
@endsection
