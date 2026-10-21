@extends('pterohub.client.layout')
@section('title','Game server hosting plans')
@section('description','Browse available game hosting plans, compare resources and explore billing options.')
@section('content')
<div class="ph-store-breadcrumb"><a href="{{ route('ph.home') }}">Home</a><span>/</span> Game hosting</div>
<header class="ph-client-heading ph-store-heading"><span class="ph-eyebrow">GAME HOSTING</span><h1>Find the right server for your game.</h1><p>Explore available configurations and compare monthly starting prices.</p></header>
<form class="ph-store-filters" action="{{ route('ph.client.catalog') }}" method="GET" role="search">
 <label><span>Search plans</span><input name="q" type="search" value="{{ request('q') }}" maxlength="100" placeholder="Game or plan name"></label>
 <label><span>Game</span><select name="game"><option value="">All games</option>@foreach($games as $game)<option value="{{ $game->slug }}" @selected(request('game') === $game->slug)>{{ $game->name }}</option>@endforeach</select></label>
 <label><span>Sort by</span><select name="sort"><option value="name" @selected($sort === 'name')>Name</option><option value="price_low" @selected($sort === 'price_low')>Price: low to high</option><option value="price_high" @selected($sort === 'price_high')>Price: high to low</option></select></label>
 <button type="submit" class="ph-client-button">Apply filters</button>
 @if(request()->hasAny(['q','game','sort']))<a class="ph-store-reset" href="{{ route('ph.client.catalog') }}">Clear</a>@endif
</form>
@if(filled(request('q')) || filled(request('game')))
<div class="ph-store-filter-state" role="status">Showing filtered results. <a href="{{ route('ph.client.catalog') }}">View all configurations</a></div>
@endif
<div class="ph-store-results"><strong>{{ $plans->total() }} {{ \Illuminate\Support\Str::plural('plan', $plans->total()) }}</strong><span>Available configurations</span></div>
@if($plans->isEmpty())<div class="ph-client-card ph-store-empty"><h2>No matching plans</h2><p>Try another search or game. Only active games and plans enabled for sale are listed.</p><a class="ph-client-button" href="{{ route('ph.client.catalog') }}">Show all plans</a></div>
@else
<div class="ph-store-grid">@foreach($plans as $plan)
<article class="ph-store-card"><div class="ph-store-card-top"><span class="ph-client-tag">{{ $plan->game_name }}</span><span class="ph-store-game-mark" aria-hidden="true">{{ strtoupper(substr($plan->game_name, 0, 2)) }}</span></div>
<h2><a href="{{ route('ph.client.plan',[$plan->game_slug,$plan->slug]) }}">{{ $plan->name }}</a></h2>
<div class="ph-store-price">@if($plan->monthly_minor !== null)<strong>€{{ number_format($plan->monthly_minor/100,2) }}</strong><span>/ month</span>@else<strong class="ph-store-unpriced">Price unavailable</strong>@endif</div>
<dl class="ph-store-specs"><div><dt>Memory</dt><dd>{{ number_format($plan->memory_mb/1024,1) }} GB</dd></div><div><dt>Storage</dt><dd>{{ number_format($plan->disk_mb/1024,1) }} GB</dd></div><div><dt>CPU</dt><dd>{{ $plan->cpu_percent }}%</dd></div><div><dt>Backups</dt><dd>{{ $plan->backups }}</dd></div></dl>
<a class="ph-client-button ph-store-card-link" href="{{ route('ph.client.plan',[$plan->game_slug,$plan->slug]) }}">View configuration</a></article>
@endforeach</div><div class="ph-store-pagination">{{ $plans->links() }}</div>
@endif
<p class="ph-client-notice">Development preview. No payments or server provisioning are enabled.</p>
@endsection
