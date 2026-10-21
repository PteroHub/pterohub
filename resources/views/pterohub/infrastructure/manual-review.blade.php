@extends('pterohub.layout')
@section('title','Provisioning Review')
@section('content')
<div class="page-heading"><div><div class="eyebrow">INFRASTRUCTURE / MANUAL PROVISIONING</div><h1>Final server creation review</h1><p class="intro">Order {{ $item->order_reference }} · Item #{{ $item->id }} · {{ $item->plan_name }}</p></div><a class="button subtle" href="{{ route('pterohub.manual.index') }}">Manual provisioning</a></div>
<div class="panel"><h2>Server details</h2>
<div class="ph-capacity-grid"><div><span>Customer</span><strong>{{ $item->customer_email }}</strong></div><div><span>Pterodactyl user</span><strong>{{ $link ? '#'.$link->remote_user_id : 'Not linked' }}</strong></div><div><span>Node</span><strong>{{ $node->name }}</strong></div><div><span>Egg ID</span><strong>#{{ $item->remote_egg_id }}</strong></div><div><span>RAM</span><strong>{{ $item->memory_mb }} MB</strong></div><div><span>Disk</span><strong>{{ $item->disk_mb }} MB</strong></div><div><span>CPU</span><strong>{{ $item->cpu_percent }}%</strong></div><div><span>Requested allocations</span><strong>{{ $item->allocations }}</strong></div></div>
<p class="helper">Estimated available RAM: {{ $capacity ? max(0,$capacity['memory_mb']-$reservations['reserved_memory_mb']).' MB' : 'Unknown' }}. Unassigned ports: {{ count($free) }}. This is not live Wings usage. A concurrent allocation can still become unavailable.</p>
@if($blockers)
<div class="notice danger"><strong>Provisioning blocked.</strong><ul>@foreach($blockers as $blocker)<li>{{ $blocker }}</li>@endforeach</ul></div>
@else
<div class="notice success">Preflight passed. This does not guarantee that Wings will install or start the server. Creation requires an explicit confirmation below.</div>
<form method="POST" action="{{ route('pterohub.manual.create') }}">@csrf
<input type="hidden" name="order_item_id" value="{{ $item->id }}"><input type="hidden" name="node_id" value="{{ $node->id }}">
<h3>Egg environment</h3><p class="helper">Variables are fetched from Pterodactyl. Review every value. Secrets are sent only to Pterodactyl and are not stored in provisioning logs.</p>
@forelse(($egg['variables'] ?? []) as $name=>$variable)
<label>{{ $variable['name'] }} ({{ $name }}){{ $variable['required'] ? ' · Required' : '' }}
<input type="{{ preg_match('/PASSWORD|SECRET|TOKEN|KEY/i',$name) ? 'password' : 'text' }}" name="env[{{ $name }}]" value="{{ $variable['default'] }}" {{ $variable['required'] ? 'required' : '' }} autocomplete="off" maxlength="4000"></label>
<p class="helper">{{ $variable['rules'] }}</p>
@empty<p class="helper">This Egg declares no environment variables.</p>@endforelse
<div class="notice danger"><strong>This action creates one real Pterodactyl server.</strong> No payment is collected. A local attempt is reserved before the API call. On timeout, do not retry: the remote outcome must be reconciled manually.</div>
<label><input type="checkbox" name="acknowledge" value="1" required> I authorize the creation of one test server on {{ $node->name }} and understand it may use real resources.</label>
<label>Type <strong>PROVISION {{ $item->id }}</strong> to confirm<input name="confirmation" type="text" required autocomplete="off" placeholder="PROVISION {{ $item->id }}"></label>
<button class="button primary" type="submit">Create one test server</button>
</form>
@endif
</div>
@endsection
