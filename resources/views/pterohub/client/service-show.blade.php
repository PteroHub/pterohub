@extends('pterohub.client.layout')
@section('title','Service #'.$service->id)
@section('content')
<nav class="ph-store-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('ph.client.dashboard') }}">Client area</a><span>/</span><a href="{{ route('ph.client.services') }}">My services</a><span>/</span>Service #{{ $service->id }}</nav>
<header class="ph-client-heading ph-service-heading"><div><span class="ph-eyebrow">HOSTING SERVICE</span><h1>{{ $service->plan_name }}</h1><p>{{ $service->game_name }} · Service #{{ $service->id }}</p></div><span class="ph-dash-status">{{ ucfirst($service->status) }}</span></header>
<div class="ph-service-grid">
  <section class="ph-dash-panel"><span class="ph-eyebrow">SERVICE DETAILS</span><h2>Overview</h2>
    <dl class="ph-service-details">
      <div><dt>Status</dt><dd>{{ ucfirst($service->status) }}</dd></div>
      <div><dt>Game</dt><dd>{{ $service->game_name }}</dd></div>
      <div><dt>Hosting plan</dt><dd>{{ $service->plan_name }}</dd></div>
      <div><dt>Created</dt><dd>{{ \Illuminate\Support\Carbon::parse($service->created_at)->format('M j, Y') }}</dd></div>
      <div><dt>Next due</dt><dd>{{ $service->next_due_at ? \Illuminate\Support\Carbon::parse($service->next_due_at)->format('M j, Y') : 'Not scheduled' }}</dd></div>
      <div><dt>Provisioning</dt><dd>{{ $service->provisioned_at ? 'Provisioned' : 'Not provisioned' }}</dd></div>
      <div><dt>Node</dt><dd>{{ $service->node_name ?: 'Not assigned' }}</dd></div>
      @if($order)<div><dt>Related order</dt><dd><a href="{{ route('ph.client.orders.show',$order->id) }}">{{ $order->reference }}</a></dd></div>@endif
    </dl>
  </section>
  <section class="ph-dash-panel"><span class="ph-eyebrow">PLAN RESOURCES</span><h2>Allocated resources</h2>
    <dl class="ph-service-details">
      <div><dt>Memory</dt><dd>{{ number_format($service->memory_mb) }} MB</dd></div>
      <div><dt>Disk</dt><dd>{{ number_format($service->disk_mb) }} MB</dd></div>
      <div><dt>CPU limit</dt><dd>{{ $service->cpu_percent }}%</dd></div>
      <div><dt>Allocations</dt><dd>{{ $service->allocations }}</dd></div>
      <div><dt>Backups</dt><dd>{{ $service->backups }}</dd></div>
      <div><dt>Databases</dt><dd>{{ $service->databases }}</dd></div>
    </dl>
  </section>
</div>
@if($service->remote_server_id)
<section class="ph-dash-panel ph-remote-panel" aria-labelledby="ph-remote-heading">
  <div class="ph-dash-panel-head"><div><span class="ph-eyebrow">PTERODACTYL CONNECTION</span><h2 id="ph-remote-heading">Your game server</h2></div><span class="ph-dash-status">{{ $remote['available'] ? 'Verified connection' : 'Status unavailable' }}</span></div>
  @if($remote['available'])
    <div class="ph-remote-grid">
      <div><span>Remote server</span><strong>#{{ $service->remote_server_id }}</strong></div>
      <div><span>Installation</span><strong>{{ $remote['installation'] }}</strong></div>
      <div><span>Suspension</span><strong>{{ is_null($remote['suspended']) ? 'Not reported' : ($remote['suspended'] ? 'Suspended' : 'Not suspended') }}</strong></div>
      <div><span>Last checked</span><strong>{{ \Illuminate\Support\Carbon::parse($remote['checked_at'])->format('M j, Y H:i') }}</strong></div>
    </div>
    @if($remote['panel_url'])
      <a class="ph-client-button ph-remote-open" href="{{ $remote['panel_url'] }}" target="_blank" rel="noopener noreferrer">Open Pterodactyl panel</a>
      <p class="ph-remote-help">Sign in with your linked Pterodactyl account. Server controls are managed in Pterodactyl.</p>
    @else
      <p class="ph-remote-help">The remote server identifier is not available. Contact support for panel access.</p>
    @endif
  @else
    <p class="ph-remote-help">{{ $remote['message'] }} Your service record remains available. Contact support if this persists.</p>
  @endif
  <p class="ph-remote-disclaimer">This read-only snapshot is cached for up to 60 seconds. The Application API does not provide live online/offline or Wings usage information. No server action is performed here.</p>
</section>
@endif
<section class="ph-dash-panel ph-service-health" aria-label="Service status information">
<span class="ph-eyebrow">SERVICE STATUS</span><h2>Service and server status</h2>
<p class="ph-remote-help">Your service status is <strong>{{ ucfirst(str_replace('_',' ',$service->status)) }}</strong>. This describes your PteroHub service record, not whether the game server is running.</p>
@if($latestServiceCheck)
<p class="ph-remote-help">Last administrator remote check: <strong>{{ $latestServiceCheck->result === 'verified' ? 'Connection verified' : 'Connection needs attention' }}</strong> ({{ $latestServiceCheck->created_at }}).</p>
@if($latestServiceCheck->result !== 'verified')<p class="ph-remote-help">A remote check could not confirm your server. Your service record has not changed. Please contact support if access is affected.</p>@endif
@else<p class="ph-remote-help">No administrator remote check has been recorded yet. The connection panel above shows the current read-only snapshot.</p>@endif
@if($renewalPlan)<p class="ph-remote-help">Administrative renewal review planned for <strong>{{ \Illuminate\Support\Carbon::parse($renewalPlan->planned_for)->format('M j, Y') }}</strong>. This is an internal planning date, not a contractual due date, invoice or payment request.</p>@endif
<p class="ph-remote-disclaimer">Billing and renewals are not automated. No payment or renewal is triggered from this page.</p>
</section>
<section class="ph-dash-panel ph-service-help"><div><span class="ph-eyebrow">NEED ASSISTANCE?</span><h2>Support for this service</h2><p>Have a question about this plan or its status? Open a ticket and mention service #{{ $service->id }}.</p></div><a class="ph-client-button" href="{{ route('ph.client.tickets.create') }}">Contact support</a></section>
<div class="ph-client-notice">Server management is handled in Pterodactyl. PteroHub does not start, stop, suspend or delete servers from the client area.</div>
<a class="ph-store-back" href="{{ route('ph.client.services') }}">Back to services</a>
@endsection
