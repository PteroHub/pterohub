@extends('pterohub.layout')
@section('title','Pterodactyl Connection')
@section('content')
<div class="page-heading"><div><div class="eyebrow">INFRASTRUCTURE / READ ONLY</div><h1>Pterodactyl Connection</h1><p class="intro">Verify your Application API and inspect the synchronized node inventory. No server actions are performed.</p></div><a class="button subtle" href="{{ route('pterohub.locations.index') }}">Locations &amp; Nodes</a></div>
<div class="panel"><div class="panel-header"><div><h2>Connection diagnostics</h2><p>HTTPS Application API access. Credentials are managed in Admin Settings and never displayed here.</p></div><span class="status-pill">Read-only</span></div>
<p><a class="button subtle" href="{{ route('pterohub.settings.group', 'pterodactyl') }}">Configure API connection</a></p>
<p><strong>Configuration:</strong> {{ $configured ? 'HTTPS URL and API key configured' : 'Missing HTTPS URL or API key' }}</p>
<form method="POST" action="{{ route('pterohub.pterodactyl.test') }}">@csrf<button class="button primary" type="submit">Test API connection</button></form>
<p class="helper">Only a GET request to the Pterodactyl node list is sent. A successful test does not verify Wings connectivity.</p>
@php($diagnostic = session('ph_diagnostic'))
@if(is_array($diagnostic))
<div class="notice {{ ($diagnostic['kind'] ?? '') === 'error' ? 'danger' : 'success' }}" role="status">
@if(($diagnostic['kind'] ?? '') === 'error')<strong>Check failed{{ isset($diagnostic['node']) ? ' for '.$diagnostic['node'] : '' }}.</strong> {{ $diagnostic['message'] ?? 'Unknown error' }}
@elseif(($diagnostic['kind'] ?? '') === 'connection')<strong>Application API connected.</strong> {{ $diagnostic['result']['message'] ?? '' }} @if(isset($diagnostic['result']['nodes_total'])) Total nodes reported: {{ $diagnostic['result']['nodes_total'] }}. @endif
@elseif(($diagnostic['kind'] ?? '') === 'allocations')<strong>{{ $diagnostic['node'] ?? 'Node' }} allocations checked.</strong> Total: {{ $diagnostic['result']['total'] ?? 0 }} · Assigned: {{ $diagnostic['result']['assigned'] ?? 0 }} · Unassigned: {{ $diagnostic['result']['unassigned'] ?? 0 }}. {{ $diagnostic['result']['message'] ?? '' }}
@elseif(($diagnostic['kind'] ?? '') === 'capacity')<strong>Node capacity report loaded.</strong> The configuration snapshot appears below.
@elseif(($diagnostic['kind'] ?? '') === 'reservations')<strong>Remote server reservations checked.</strong> Read-only snapshot appears below.
@elseif(($diagnostic['kind'] ?? '') === 'order-readiness')<strong>Order-item safety review completed.</strong> {{ (int) ($diagnostic['blocking'] ?? 0) }} blockers. No provisioning performed.
@elseif(($diagnostic['kind'] ?? '') === 'preflight')<strong>Provisioning preflight completed.</strong> {{ (int) ($diagnostic['blocking'] ?? 0) }} configuration blockers found. No server action performed.
@endif
</div>
@endif
</div>
@if(is_array($diagnostic) && ($diagnostic['kind'] ?? '') === 'capacity')
@php($capacity = $diagnostic['result'] ?? [])
@php($known = $diagnostic['services'] ?? [])
<div class="panel">
<div class="panel-header"><div><h2>Node capacity report: {{ $diagnostic['node'] ?? 'Node' }}</h2><p>Read-only configuration snapshot, cached for 60 seconds. No server placement decision is made.</p></div><span class="status-pill">Advisory only</span></div>
<div class="ph-capacity-grid">
<div><span>Configured RAM</span><strong>{{ number_format((int) ($capacity['memory_mb'] ?? 0)) }} MB</strong></div>
<div><span>Configured disk</span><strong>{{ number_format((int) ($capacity['disk_mb'] ?? 0)) }} MB</strong></div>
<div><span>RAM overallocation</span><strong>{{ isset($capacity['memory_overallocate']) ? $capacity['memory_overallocate'].'%' : 'Unknown' }}</strong></div>
<div><span>Disk overallocation</span><strong>{{ isset($capacity['disk_overallocate']) ? $capacity['disk_overallocate'].'%' : 'Unknown' }}</strong></div>
<div><span>Maintenance</span><strong>{{ !empty($capacity['maintenance']) ? 'Enabled' : 'Disabled' }}</strong></div>
<div><span>Enabled for sale in PteroHub</span><strong>{{ !empty($diagnostic['enabled_for_sale']) ? 'Yes' : 'No' }}</strong></div>
<div><span>PteroHub-linked services</span><strong>{{ (int) ($known['service_count'] ?? 0) }}</strong></div>
<div><span>PteroHub-known RAM reservations</span><strong>{{ number_format((int) ($known['reserved_memory_mb'] ?? 0)) }} MB</strong></div>
<div><span>PteroHub-known disk reservations</span><strong>{{ number_format((int) ($known['reserved_disk_mb'] ?? 0)) }} MB</strong></div>
</div>
<div class="notice">Not a live usage measurement. PteroHub-known reservations exclude servers created directly in Pterodactyl or by other systems. Available RAM, disk, CPU and Wings health cannot be confirmed here. A product fitting the configured limits is NOT a guarantee of capacity.</div>
<h3>Products associated with this node</h3>
@if(empty($diagnostic['plans']))<p class="helper">No hosting products are associated with this node.</p>
@else
<div class="table-scroll"><table class="data-table"><thead><tr><th>Product</th><th>RAM</th><th>Disk</th><th>CPU</th><th>Ports</th><th>Publication</th><th>Configured size check</th></tr></thead><tbody>
@foreach($diagnostic['plans'] as $plan)
@php($fits = (int) $plan['memory_mb'] <= (int) ($capacity['memory_mb'] ?? 0) && (int) $plan['disk_mb'] <= (int) ($capacity['disk_mb'] ?? 0))
<tr><td><strong>{{ $plan['name'] }}</strong></td><td>{{ $plan['memory_mb'] }} MB</td><td>{{ $plan['disk_mb'] }} MB</td><td>{{ $plan['cpu_percent'] }}%</td><td>{{ $plan['allocations'] }}</td><td>{{ !empty($plan['active']) ? 'Published' : 'Draft' }}</td><td>{{ $fits ? 'Within configured RAM/disk limits' : 'Exceeds configured RAM/disk limits' }}</td></tr>
@endforeach
</tbody></table></div>
@endif
<p class="helper">CPU capacity and free port availability are not established by this report. The configuration check compares each plan against the node's total configured RAM/disk, not its remaining free capacity.</p>
</div>
@endif
@if(is_array($diagnostic) && ($diagnostic['kind'] ?? '') === 'reservations')
<div class="panel">
<div class="panel-header"><div><h2>Remote server reservations: {{ $diagnostic['node'] ?? 'Node' }}</h2><p>Read-only inventory across ALL Pterodactyl servers, including those created outside PteroHub.</p></div><span class="status-pill">Advisory only</span></div>
<div class="ph-capacity-grid">
<div><span>Existing servers</span><strong>{{ (int) ($diagnostic['reservations']['servers'] ?? 0) }}</strong></div>
<div><span>Reserved RAM</span><strong>{{ number_format((int) ($diagnostic['reservations']['reserved_memory_mb'] ?? 0)) }} MB</strong></div>
<div><span>Reserved disk</span><strong>{{ number_format((int) ($diagnostic['reservations']['reserved_disk_mb'] ?? 0)) }} MB</strong></div>
<div><span>Estimated unreserved RAM</span><strong>{{ number_format((int) ($diagnostic['remaining_ram_mb'] ?? 0)) }} MB</strong></div>
<div><span>Estimated unreserved disk</span><strong>{{ number_format((int) ($diagnostic['remaining_disk_mb'] ?? 0)) }} MB</strong></div>
<div><span>Unassigned allocations</span><strong>{{ (int) ($diagnostic['allocations']['unassigned'] ?? 0) }}</strong></div>
</div>
<div class="notice">These are configured reservations, NOT actual free RAM/disk or Wings health. The estimate ignores overallocation and may be up to 60 seconds old. Never use this alone to authorize provisioning.</div>
</div>
@endif
@if(is_array($diagnostic) && ($diagnostic['kind'] ?? '') === 'order-readiness')
<div class="panel">
<div class="panel-header"><div><h2>Order-item readiness: #{{ (int) ($diagnostic['item_id'] ?? 0) }}</h2><p>{{ $diagnostic['order'] ?? '' }} · {{ $diagnostic['plan'] ?? '' }} · {{ $diagnostic['node'] ?? '' }}</p></div><span class="status-pill">{{ (int) ($diagnostic['blocking'] ?? 0) }} blockers</span></div>
<div class="notice">{{ (int) ($diagnostic['blocking'] ?? 0) === 0 ? 'Preliminary checks passed.' : 'This order item has blockers.' }} This is NOT authorization to create a server. Customer-to-Pterodactyl user mapping, Egg variables, Wings health, payment policy, idempotency and live allocation reservation remain unverified.</div>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Check</th><th>Result</th></tr></thead><tbody>
@foreach(($diagnostic['checks'] ?? []) as $check)<tr><td>{{ $check['label'] }}</td><td><strong>{{ $check['pass'] ? 'Pass' : 'Blocked' }}</strong></td></tr>@endforeach
</tbody></table></div>
</div>
@endif
@if(is_array($diagnostic) && ($diagnostic['kind'] ?? '') === 'preflight')
<div class="panel">
<div class="panel-header"><div><h2>Provisioning preflight: {{ $diagnostic['plan'] ?? 'Product' }}</h2><p>Node: {{ $diagnostic['node'] ?? 'Unknown' }}. Read-only configuration checklist.</p></div><span class="status-pill">{{ (int) ($diagnostic['blocking'] ?? 0) }} blockers</span></div>
@if((int) ($diagnostic['blocking'] ?? 0) === 0)
<div class="notice success">Configuration checks passed. This is NOT authorization to provision: live Pterodactyl reservations, Wings health, actual available resources and duplicate-service protection still need verification.</div>
@else
<div class="notice danger">Not ready for provisioning. Resolve the configuration blockers below before attempting a server creation in a future release.</div>
@endif
<div class="table-scroll"><table class="data-table"><thead><tr><th>Check</th><th>Result</th><th>Action if blocked</th></tr></thead><tbody>
@foreach(($diagnostic['checks'] ?? []) as $check)
<tr><td>{{ $check['label'] }}</td><td><strong>{{ $check['pass'] ? 'Pass' : 'Blocked' }}</strong></td><td>{{ $check['pass'] ? 'No action required' : $check['action'] }}</td></tr>
@endforeach
</tbody></table></div>
<p class="helper">Unassigned allocations at scan time: {{ (int) ($diagnostic['allocations']['unassigned'] ?? 0) }}. {{ $diagnostic['note'] ?? '' }}</p>
<p><a class="button subtle" href="{{ route('pterohub.locations.index') }}">Manage node assignments</a> <a class="button subtle" href="{{ route('pterohub.products.index') }}">Hosting Products</a></p>
</div>
@endif
<div class="panel">
<div class="panel-header"><div><h2>Provisioning preflight</h2><p>Check whether a hosting product is configured for a synchronized node. No server is created and no resources are reserved.</p></div><span class="status-pill">Read-only</span></div>
@if($nodes->isEmpty() || $plans->isEmpty())
<p class="helper">Synchronize a node and create a hosting product to run preflight.</p>
@else
<form method="POST" action="{{ route('pterohub.pterodactyl.preflight') }}">
@csrf
<div class="ph-preflight-fields">
<label>Hosting product<select name="plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->memory_mb }} MB RAM)</option>@endforeach</select></label>
<label>Target node<select name="node_id" required>@foreach($nodes as $node)<option value="{{ $node->id }}">{{ $node->name }}</option>@endforeach</select></label>
</div>
<button class="button primary" type="submit">Run preflight</button>
</form>
@endif
<p class="helper">A passed preflight is only a configuration review, not a guarantee of capacity or server readiness. No automatic provisioning is enabled.</p>
</div>
<div class="panel">
<div class="panel-header"><div><h2>Provisioning safety gate</h2><p>Inspect existing Pterodactyl server reservations and review a confirmed order item. Read-only: no user, service, server or payment is created.</p></div><span class="status-pill">No provisioning</span></div>
@if($nodes->isEmpty())<p class="helper">Synchronize nodes first.</p>@else
<form method="POST" action="{{ route('pterohub.pterodactyl.reservations') }}">@csrf
<label>Node<select name="node_id" required>@foreach($nodes as $node)<option value="{{ $node->id }}">{{ $node->name }}</option>@endforeach</select></label>
<button class="button subtle" type="submit">Check existing server reservations</button>
</form>
@if($orderItems->isNotEmpty())
<form method="POST" action="{{ route('pterohub.pterodactyl.order-readiness') }}">@csrf
<div class="ph-preflight-fields">
<label>Confirmed order item<select name="order_item_id" required>@foreach($orderItems as $item)<option value="{{ $item->id }}">#{{ $item->id }} · {{ $item->reference }} · {{ $item->plan_name }}</option>@endforeach</select></label>
<label>Target node<select name="node_id" required>@foreach($nodes as $node)<option value="{{ $node->id }}">{{ $node->name }}</option>@endforeach</select></label>
</div><button class="button primary" type="submit">Review order-item readiness</button>
</form>
@else<p class="helper">No confirmed order items available. Create and confirm a test order to review its readiness.</p>@endif
@endif
<p class="helper">A passed review is not authorization to provision. No Pterodactyl mutation requests are used in this release.</p>
</div>
<div class="panel"><div class="panel-header"><div><h2>Synchronized inventory</h2><p>{{ $nodes->count() }} nodes · {{ $eggCount }} Eggs · {{ $linked }} product-to-node assignments</p></div></div>
<div class="notice">Inventory is updated through <strong>Synchronize Eggs &amp; nodes</strong> on the Admin Overview. This diagnostic page does not modify your inventory.</div>
@if($nodes->isEmpty())<p class="helper">No synchronized nodes. Run the catalog synchronization first.</p>
@else
<div class="table-scroll"><table class="data-table"><thead><tr><th>Node</th><th>FQDN</th><th>Remote ID</th><th>Last sync</th><th>Allocations</th></tr></thead><tbody>
@foreach($nodes as $node)
<tr><td><strong>{{ $node->name }}</strong></td><td>{{ $node->fqdn ?: 'Not set' }}</td><td>#{{ $node->remote_id }}</td><td>{{ $node->last_synced_at ?: 'Never' }}</td><td><form method="POST" action="{{ route('pterohub.pterodactyl.node') }}">@csrf<input type="hidden" name="node_id" value="{{ $node->id }}"><button class="button subtle" type="submit">Check allocations</button></form><form method="POST" action="{{ route('pterohub.pterodactyl.capacity') }}" class="ph-capacity-form">@csrf<input type="hidden" name="node_id" value="{{ $node->id }}"><button class="button subtle" type="submit">Check capacity</button></form></td></tr>
@endforeach
</tbody></table></div>
@endif
<p class="helper">Allocation results are cached for 60 seconds. API rate limits are respected; repeated clicks do not force another scan. Allocation counts are informational. Capacity checks show configured node limits and PteroHub-known reservations only. They do not verify live Wings usage, other Pterodactyl servers or free CPU.</p>
</div>
<div class="panel"><h2>Safety boundaries</h2><p>This release only reads the Pterodactyl API and preserves explicitly selected local sale eligibility in Locations & Nodes. It does not create, suspend, start, stop, delete or migrate Pterodactyl servers. It does not enable payments or automatically mark nodes as available for sale.</p></div>
@endsection
