<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="color-scheme" content="dark"><title>@yield('title', 'Administration') · PteroHub</title>



<link rel="stylesheet" href="{{ asset('assets/pterohub.css') }}?v=rc1.02">
</head>
<body class="@yield('bodyclass','admin-page')">
<a class="ph-skip-link" href="#main-content">Skip to main content</a>
@if (request()->routeIs('pterohub.login'))
  <main class="auth-shell" id="main-content">@if($errors->any())<div class="notice danger" role="alert">{{ $errors->first() }}</div>@endif @yield('content')</main>
@else
<div class="app-shell" id="ph-app-shell">
<button class="ph-mobile-overlay" type="button" data-ph-menu-close aria-label="Close navigation" tabindex="-1"></button>
<aside class="sidebar" id="ph-sidebar" aria-label="Main navigation">
  <button type="button" class="ph-sidebar-close" data-ph-menu-close aria-label="Close menu">Close</button><a class="logo" href="{{ route('pterohub.admin.dashboard') }}"><img src="{{ asset('assets/pterohub-logo.webp') }}" alt="" width="40" height="40" class="ph-brand-image"><span>Ptero<span class="logo-strong">Hub</span><small>HOSTING PLATFORM</small></span></a>
  <nav class="ph-grouped-nav" aria-label="Administration">
  <div class="sidebar-group-label">DASHBOARD</div>
<a class="nav-link" href="{{ route('pterohub.admin.dashboard') }}">Overview</a>
  <div class="sidebar-group-label">CATALOG</div>
  <a class="nav-link" href="{{ route('pterohub.admin.dashboard') }}#games">Games</a>
  <a class="nav-link" href="{{ route('pterohub.admin.dashboard') }}#eggs">Eggs</a>
  <a class="nav-link" href="{{ route('pterohub.admin.dashboard') }}#plans">Hosting Plans</a>
  <a class="nav-link" href="{{ route('pterohub.products.index') }}">Hosting Products</a>

  <div class="sidebar-group-label">INFRASTRUCTURE</div>
  <a class="nav-link" href="{{ route('pterohub.locations.index') }}">Locations &amp; Nodes</a>
  <a class="nav-link" href="{{ route('pterohub.pterodactyl.index') }}">Pterodactyl Connection</a>
  <a class="nav-link" href="{{ route('pterohub.manual.index') }}">Manual Provisioning</a>

  <div class="sidebar-group-label">COMMERCE</div>
  <a class="nav-link" href="{{ route('pterohub.customers.index') }}">Customers</a>
  <a class="nav-link" href="{{ route('pterohub.orders.index') }}">Orders</a>
  <a class="nav-link" href="{{ route('pterohub.services.index') }}">Services</a>
  <a class="nav-link" href="{{ route('pterohub.invoices.index') }}">Invoices</a>
  <a class="nav-link" href="{{ route('pterohub.billing.index') }}">Billing overview</a>
  <a class="nav-link" href="{{ route('pterohub.sandbox.admin') }}">Sandbox payments</a>

  <div class="sidebar-group-label">SUPPORT</div>
  <a class="nav-link" href="{{ route('pterohub.tickets.index') }}">Support Tickets</a>

  <div class="sidebar-group-label">SYSTEM</div>
  <a class="nav-link" href="{{ route('pterohub.notifications.index') }}">Notifications</a>
  <a class="nav-link" href="{{ route('pterohub.settings.index') }}">Settings</a>
</nav>
<div class="sidebar-bottom"><div class="support-block"><div class="support-symbol"></div><strong>Development mode</strong><span>Sandbox payments only. No live charges or automatic server provisioning.</span></div><form method="POST" action="{{ route('pterohub.logout') }}">@csrf<button class="signout" type="submit"> &nbsp; Sign out</button></form></div>
</aside>
<div class="workspace"><header class="topbar"><button class="ph-menu-toggle" type="button" data-ph-menu-toggle aria-controls="ph-sidebar" aria-expanded="false" aria-label="Open navigation">Menu</button><div class="breadcrumb">Workspace <span>/</span> Administration</div><div class="top-right"><span class="env-indicator"><span class="pulse"></span> Development</span><span class="user-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A',0,1)) }}</span></div></header><main class="page-container" id="main-content">@if(session('ok'))<div class="notice success" role="status">{{ session('ok') }}</div>@endif @if($errors->any())<div class="notice danger" role="alert">{{ $errors->first() }}</div>@endif @yield('content')</main><footer class="footer">© {{ date('Y') }} PteroHub <span>·</span> Administration v1.0 <span>·</span> Sandbox testing only</footer></div>
</div>
@endif


<script src="{{ asset('assets/pterohub.js') }}?v=rc1.02" defer></script>
</body></html>
