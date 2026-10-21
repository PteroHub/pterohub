@extends('pterohub.client.layout')
@section('title','Game Server Hosting')
@section('description','Discover game server hosting plans and manage your hosting account in one place.')
@section('pageclass','ph-home')
@section('content')
<section class="ph-home-hero">
  <div class="ph-home-hero-copy"><span class="ph-eyebrow">GAME SERVER HOSTING PLATFORM</span><h1>Your games.<br><em>Your servers.</em><br>Your control.</h1><p>Find a hosting plan for your favorite game and manage everything from a single, easy-to-use client portal.</p>
  <div class="ph-home-actions"><a class="ph-client-button" href="{{ route('ph.client.catalog') }}">Explore game hosting</a>@guest<a class="ph-client-button ph-client-button-quiet" href="{{ route('ph.client.login') }}">Client sign in</a>@else<a class="ph-client-button ph-client-button-quiet" href="{{ auth()->user()->is_pterohub_admin ? route('pterohub.admin.dashboard') : route('ph.client.dashboard') }}">Open dashboard</a>@endguest</div>
  <p class="ph-home-availability">Development preview · Orders are drafts; no payments are collected.</p></div>
  <div class="ph-home-hero-visual" aria-hidden="true"><div class="ph-home-orb"></div><div class="ph-home-console"><div class="ph-home-console-head"><span></span><span></span><span></span><b>SERVER MANAGEMENT</b></div><div class="ph-home-console-body"><div class="ph-home-console-icon"><img src="{{ asset('assets/pterohub-logo.webp') }}" alt="" width="40" height="40" class="ph-brand-image"></div><strong>One home for your game servers</strong><small>Browse plans and manage your hosting account</small><div class="ph-home-console-bars"><i></i><i></i><i></i></div><div class="ph-home-console-pill">DEVELOPMENT PREVIEW</div></div></div></div>
</section>
<section class="ph-home-section" id="games"><div class="ph-home-section-head"><div><span class="ph-eyebrow">GAME LIBRARY</span><h2>Find your next server</h2><p>Explore games currently listed in the hosting catalog.</p></div><a href="{{ route('ph.client.catalog') }}">Browse all plans</a></div>
@if($games->isNotEmpty())<div class="ph-home-game-grid">@foreach($games as $game)<a class="ph-home-game" href="{{ route('ph.client.catalog',['game'=>$game->slug]) }}"><span class="ph-home-game-icon">{{ strtoupper(substr($game->name,0,2)) }}</span><strong>{{ $game->name }}</strong><small>{{ $game->engine ?: 'Game hosting' }}</small></a>@endforeach</div>
@else<div class="ph-home-placeholder"><strong>Games are being prepared</strong><p>Once games are activated in the administration panel, they'll be shown here.</p></div>@endif
</section>
<section class="ph-home-section" id="plans"><div class="ph-home-section-head"><div><span class="ph-eyebrow">HOSTING PLANS</span><h2>Choose a plan that fits</h2><p>Available plans are loaded from the existing catalog.</p></div><a href="{{ route('ph.client.catalog') }}">See complete catalog</a></div>
@if($plans->isNotEmpty())<div class="ph-home-plan-grid">@foreach($plans as $plan)<article class="ph-home-plan"><span class="ph-client-tag">{{ $plan->game_name }}</span><h3>{{ $plan->name }}</h3><div class="ph-home-plan-price">@if($plan->monthly_minor !== null)€{{ number_format($plan->monthly_minor/100,2) }}<small>/month</small>@else<small>Pricing coming soon</small>@endif</div><div class="ph-home-plan-spec"><span>RAM</span><strong>{{ $plan->memory_mb }} MB</strong></div><div class="ph-home-plan-spec"><span>Storage</span><strong>{{ $plan->disk_mb }} MB</strong></div><div class="ph-home-plan-spec"><span>CPU</span><strong>{{ $plan->cpu_percent }}%</strong></div><a class="ph-client-button" href="{{ route('ph.client.plan',[$plan->game_slug,$plan->slug]) }}">View plan</a></article>@endforeach</div>
@else<div class="ph-home-placeholder"><strong>Plans are coming soon</strong><p>Hosting plans will be visible once they're enabled for sale in the administration panel.</p></div>@endif
</section>
<section class="ph-home-steps" aria-labelledby="ph-steps-title">
  <div class="ph-home-steps-intro"><span class="ph-eyebrow">HOW IT WORKS</span><h2 id="ph-steps-title">From choosing a plan to managing your account</h2><p>Explore available hosting options and manage your requests in one place.</p></div>
  <div class="ph-home-steps-grid">
    <article class="ph-home-step"><span class="ph-home-step-number">01</span><h3>Explore the catalog</h3><p>Find a supported game and compare the available plans, resources and billing periods.</p></article>
    <article class="ph-home-step"><span class="ph-home-step-number">02</span><h3>Review your selection</h3><p>Add configurations to your cart, review the totals and submit a draft order.</p></article>
    <article class="ph-home-step"><span class="ph-home-step-number">03</span><h3>Use your client area</h3><p>Track orders, review invoices and services, and contact support from your dashboard.</p></article>
  </div>
</section>
<section class="ph-home-help" aria-labelledby="ph-home-help-title">
  <div><span class="ph-eyebrow">HELP CENTER</span><h2 id="ph-home-help-title">Questions about getting started?</h2><p>Learn how to choose a plan, review a draft order and find support in your client area.</p></div>
  <a class="ph-client-button ph-client-button-quiet" href="{{ route('ph.help') }}">Visit help center</a>
</section>
<section class="ph-home-bottom"><div><span class="ph-eyebrow">YOUR ACCOUNT</span><h2>Everything in one client area</h2><p>Review services, track orders and invoices, update your billing profile, and contact support from one dashboard.</p></div><a class="ph-client-button" href="{{ auth()->check() && !auth()->user()->is_pterohub_admin ? route('ph.client.dashboard') : route('ph.client.login') }}">Go to client area</a></section>
@endsection
