@extends('pterohub.layout')
@section('title','Locations & Nodes')
@section('content')

<div class="phl-head"><div><div class="phl-eyebrow">INFRASTRUCTURE / INVENTORY</div><h1>Locations &amp; Nodes</h1><p>Manage local node eligibility for hosting products. No server provisioning is enabled.</p></div><a class="phl-btn phl-secondary" href="{{ route('pterohub.admin.dashboard') }}">Overview</a></div>
<div class="phl-cards"><div class="phl-card"><span>Synced nodes</span><b>{{ $nodes->count() }}</b><small>From Pterodactyl Application API</small></div><div class="phl-card"><span>Hosting products</span><b>{{ $plans->count() }}</b><small>Draft or existing catalog entries</small></div><div class="phl-card"><span>Node assignments</span><b>{{ $assignments->flatten(1)->count() }}</b><small>Plan-to-node eligibility only</small></div></div>
<section class="phl-panel"><h2>Available infrastructure</h2><p>Nodes are read from the local PteroHub inventory. To refresh the inventory, use the existing Pterodactyl Sync action in the Overview.</p>
@if($nodes->isEmpty())<div class="phl-empty"><strong>No nodes connected yet</strong><span>That matches your current Pterodactyl 2.0 setup. Add a location and node in Pterodactyl, then use Catalog Sync. This page will update automatically.</span></div>
@else <div class="phl-table-wrap"><table class="phl-table"><thead><tr><th>Node</th><th>FQDN</th><th>Remote location ID</th><th>Sale setting</th><th>Manage sale eligibility</th></tr></thead><tbody>@foreach($nodes as $node)<tr><td><b>{{ $node->name }}</b><small>#{{ $node->remote_id }}</small></td><td>{{ $node->fqdn ?: '—' }}</td><td>{{ $node->remote_location_id ?: '—' }}</td><td><span class="phl-pill">{{ $node->enabled_for_sale ? 'Enabled' : 'Disabled' }}</span></td>
<td><form method="POST" action="{{ route('pterohub.locations.node.sale', $node->id) }}">@csrf
<input type="hidden" name="enabled_for_sale" value="{{ $node->enabled_for_sale ? '0' : '1' }}">
<input type="hidden" name="confirm" value="CONFIRM">
<button type="submit" class="phl-btn phl-secondary">{{ $node->enabled_for_sale ? 'Disable for sale' : 'Enable for sale' }}</button>
</form></td></tr>@endforeach</tbody></table></div>@endif</section>
<div class="phl-note">Sale eligibility is a PteroHub-local setting only. Enabling it does not create servers, reserve ports, activate payments, or verify Wings health.</div>
<section class="phl-panel"><h2>Product availability</h2><p>Choose which synchronized nodes are eligible for each plan. Products with order or service history may gain nodes, but existing node assignments cannot be removed here. These changes do not allocate ports, reserve RAM, publish products, or activate servers.</p>
@if($plans->isEmpty())<div class="phl-empty"><strong>No hosting products yet</strong><span>Create a draft in Hosting Products to configure eligible nodes.</span></div>
@else <div class="phl-grid">@foreach($plans as $plan)
@php($selected = ($assignments[$plan->id] ?? collect())->pluck('pterodactyl_node_id')->map(fn($v)=>(int)$v)->all())
<form class="phl-product" method="POST" action="{{ route('pterohub.locations.update',$plan->id) }}">@csrf
<div class="phl-product-title"><strong>{{ $plan->name }}</strong><span class="phl-pill">Node eligibility</span></div><span class="phl-sub">{{ $plan->game_name }} · {{ $plan->memory_mb }} MB RAM · {{ $plan->cpu_percent }}% CPU</span>
@if($nodes->isEmpty())<div class="phl-hint">Add a node to Pterodactyl before assigning a location.</div>
@else <div class="phl-choices">@foreach($nodes as $node)<label><input type="checkbox" name="node_ids[]" value="{{ $node->id }}" {{ in_array((int)$node->id,$selected,true)?'checked':'' }}><span>{{ $node->name }} <small>({{ $node->fqdn ?: '#'.$node->remote_id }})</small></span></label>@endforeach</div><button type="submit" class="phl-btn">Save node assignments</button>@endif
</form>@endforeach</div>@endif</section>
<div class="phl-note">Infrastructure mapping is preparatory. Capacity checks do not guarantee live free resources or Wings health. No automatic activation is enabled.</div>
@endsection
