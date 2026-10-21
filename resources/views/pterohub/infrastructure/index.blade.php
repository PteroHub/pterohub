@extends('pterohub.layout')
@section('title','Capacity & Allocations')
@section('content')
<div class="page-heading"><div><div class="eyebrow">INFRASTRUCTURE / READ ONLY</div><h1>Capacity & Allocations</h1><p class="intro">Conservative eligibility preflight. Never reserves resources or provisions servers.</p></div><a class="button subtle" href="{{ route('pterohub.locations.index') }}">Locations & Nodes </a></div>
<div class="panel"><div class="panel-header"><div><h2>Infrastructure status</h2><p>{{ $nodes->count() }} synchronized nodes · {{ $plans->count() }} hosting products</p></div><span class="status-pill">Read-only</span></div>
@if(!$nodes->count())<div class="notice">No Pterodactyl nodes yet. All products remain unavailable. Add a node in Pterodactyl and synchronize the catalog.</div>@endif
<div class="table-scroll"><table class="data-table"><thead><tr><th>Product</th><th>Resources requested</th><th>Check</th><th>Assigned nodes</th></tr></thead><tbody>
@forelse($plans as $plan)<tr><td><strong>{{ $plan->name }}</strong></td><td>{{ $plan->memory_mb }} MB RAM · {{ $plan->disk_mb }} MB disk · {{ $plan->cpu_percent }}% CPU</td><td><span class="status-pill">{{ $checks[$plan->id]['state'] }}</span></td><td>@forelse($checks[$plan->id]['nodes'] as $node)<div><strong>{{ $node['name'] }}</strong> — {{ $node['state'] }}<br><small class="helper">{{ $node['reason'] }}</small></div>@empty <span class="helper">No assigned nodes</span> @endforelse</td></tr>@empty<tr><td colspan="4">Create a draft hosting product first.</td></tr>@endforelse
</tbody></table></div><div class="notice">Capacity cannot be marked available until API allocation inventory, existing server reservations, disk/CPU policies and node health checks are implemented. No live customer orders are allowed.</div></div>
@endsection
