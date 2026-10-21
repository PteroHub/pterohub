<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="dark"><meta name="theme-color" content="#11131d">
<title>@yield('title', 'Game Server Hosting') · {{ config('app.name', 'PteroHub') }}</title>
<meta name="description" content="@yield('description', 'Explore game server hosting plans and manage your services.')">
<link rel="stylesheet" href="{{ asset('assets/pterohub.css') }}?v=rc1.02">
<script src="{{ asset('assets/pterohub.js') }}?v=rc1.02" defer></script>
</head>
<body class="ph-client @yield('pageclass')">
<a class="ph-skip-link" href="#main-content">Skip to main content</a>
<div class="ph-client-shell">
<header class="ph-client-header">
  <div class="ph-client-header-inner">
    <a class="ph-client-brand" href="{{ route('ph.home') }}" aria-label="{{ config('app.name', 'PteroHub') }} home"><img src="{{ asset('assets/pterohub-logo.webp') }}" alt="" width="40" height="40" class="ph-brand-image"><span>{{ config('app.name', 'PteroHub') }}<small>GAME HOSTING</small></span></a>
    <button type="button" class="ph-client-nav-toggle" aria-label="Toggle main navigation" data-client-nav-toggle aria-controls="ph-client-nav" aria-expanded="false">Menu</button>
    <nav class="ph-client-nav" id="ph-client-nav" aria-label="Main navigation">
      <a href="{{ route('ph.home') }}" @if(request()->routeIs('ph.home')) aria-current="page" @endif>Home</a>
      <a href="{{ route('ph.client.catalog') }}" @if(request()->routeIs('ph.client.catalog')) aria-current="page" @endif>Game hosting</a>
      <a href="{{ route('ph.help') }}" @if(request()->routeIs('ph.help')) aria-current="page" @endif>Help</a>
      <a href="{{ route('ph.cart.show') }}" @if(request()->routeIs('ph.cart.*')) aria-current="page" @endif>Cart ({{ array_sum(array_filter((array) session('ph_cart_v1', []), 'is_int')) }})</a>
      @auth
        @if(!auth()->user()->is_pterohub_admin)
          <a href="{{ route('ph.client.dashboard') }}" @if(request()->routeIs('ph.client.dashboard')) aria-current="page" @endif>Client area</a>
          <form method="POST" action="{{ route('ph.client.logout') }}">@csrf<button type="submit">Sign out</button></form>
        @else
          <a href="{{ route('pterohub.admin.dashboard') }}">Administration</a>
        @endif
      @else
        <a href="{{ route('ph.client.login') }}">Sign in</a>
        @if(config('pterohub_client.registration_enabled'))<a class="ph-client-nav-cta" href="{{ route('ph.client.register') }}">Create account</a>@endif
      @endauth
    </nav>
  </div>
</header>
@php
  $clientSectionNames = [
    'ph.client.dashboard' => 'Overview',
    'ph.client.getting-started' => 'Getting started',
    'ph.client.services*' => 'My services',
    'ph.client.orders*' => 'Orders',
    'ph.client.invoices*' => 'Invoices',
    'ph.client.tickets*' => 'Support tickets',
    'ph.client.profile' => 'Billing profile',
    'ph.client.security*' => 'Account security',
  ];
  $isClientArea = request()->routeIs(...array_keys($clientSectionNames));
  $clientSectionLabel = 'Account';
  foreach ($clientSectionNames as $routePattern => $sectionName) {
    if (request()->routeIs($routePattern)) {
      $clientSectionLabel = $sectionName;
      break;
    }
  }
@endphp
<div class="{{ $isClientArea ? 'ph-client-area' : 'ph-public-area' }}">
@if($isClientArea)
<details class="ph-client-mobile-sections" aria-label="Client account sections">
  <summary>Client sections <span class="ph-client-mobile-current">{{ $clientSectionLabel }}</span></summary>
  <nav aria-label="Client account sections" class="ph-client-mobile-links">
    <a href="{{ route('ph.client.dashboard') }}" @if(request()->routeIs('ph.client.dashboard')) aria-current="page" @endif>Overview</a>
    <a href="{{ route('ph.client.getting-started') }}" @if(request()->routeIs('ph.client.getting-started')) aria-current="page" @endif>Getting started</a>
    <a href="{{ route('ph.client.services') }}" @if(request()->routeIs('ph.client.services*')) aria-current="page" @endif>My services</a>
    <a href="{{ route('ph.client.orders') }}" @if(request()->routeIs('ph.client.orders*')) aria-current="page" @endif>Orders</a>
    <a href="{{ route('ph.client.invoices') }}" @if(request()->routeIs('ph.client.invoices*')) aria-current="page" @endif>Invoices</a>
    <a href="{{ route('ph.client.tickets') }}" @if(request()->routeIs('ph.client.tickets*')) aria-current="page" @endif>Support tickets</a>
    <a href="{{ route('ph.client.profile') }}" @if(request()->routeIs('ph.client.profile')) aria-current="page" @endif>Billing profile</a>
    <a href="{{ route('ph.client.security') }}" @if(request()->routeIs('ph.client.security*')) aria-current="page" @endif>Account security</a>
    <a href="{{ route('ph.client.catalog') }}">Browse hosting</a>
    <a href="{{ route('ph.cart.show') }}">Shopping cart</a>
  </nav>
</details>
<aside class="ph-client-side" aria-label="Client account navigation">
  <div class="ph-client-side-label">YOUR WORKSPACE</div>
  <a href="{{ route('ph.client.dashboard') }}" @class(['is-active'=>request()->routeIs('ph.client.dashboard')])>Overview</a>
  <a href="{{ route('ph.client.getting-started') }}" @class(['is-active'=>request()->routeIs('ph.client.getting-started')])>Getting started</a>
  <a href="{{ route('ph.client.services') }}" @class(['is-active'=>request()->routeIs('ph.client.services*')])>My services</a>
  <a href="{{ route('ph.client.orders') }}" @class(['is-active'=>request()->routeIs('ph.client.orders*')])>Orders</a>
  <a href="{{ route('ph.client.invoices') }}" @class(['is-active'=>request()->routeIs('ph.client.invoices*')])>Invoices</a>
  <a href="{{ route('ph.client.tickets') }}" @class(['is-active'=>request()->routeIs('ph.client.tickets*')])>Support tickets</a>
  <div class="ph-client-side-label ph-client-side-divider">ACCOUNT</div>
  <a href="{{ route('ph.client.profile') }}" @class(['is-active'=>request()->routeIs('ph.client.profile')])>Billing profile</a>
  <a href="{{ route('ph.client.security') }}" @class(['is-active'=>request()->routeIs('ph.client.security*')])>Account security</a>
  <a href="{{ route('ph.client.catalog') }}">Browse hosting</a>
  <a href="{{ route('ph.cart.show') }}">Shopping cart</a>
</aside>
@endif
<main class="ph-client-main" id="main-content">
@if(session('ok'))<div class="ph-client-alert" role="status">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="ph-client-alert ph-client-error" role="alert">{{ $errors->first() }}</div>@endif
@yield('content')
</main>
</div>
<footer class="ph-client-footer"><div class="ph-client-footer-inner"><span>© {{ date('Y') }} {{ config('app.name', 'PteroHub') }}. All rights reserved.</span><span><a href="{{ route('ph.help') }}">Help center</a> · Game server hosting · Development preview</span></div></footer>
</div>
</body></html>
