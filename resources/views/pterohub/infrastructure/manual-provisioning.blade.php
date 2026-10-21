@extends('pterohub.layout')
@section('title','Manual Provisioning')
@section('content')
<div class="page-heading"><div><div class="eyebrow">INFRASTRUCTURE / CONTROLLED TEST</div><h1>Manual provisioning</h1><p class="intro">Link an existing Pterodactyl user, review an order and explicitly authorize a single server creation.</p></div><a class="button subtle" href="{{ route('pterohub.pterodactyl.index') }}">Connection diagnostics</a></div>
<div class="notice danger"><strong>Real server creation is enabled on this page only.</strong> The final confirmation sends a POST to Pterodactyl. No automatic order or payment trigger exists. Use a disposable test order, user and node. Never press Create twice.</div>
@if(session('ok'))<div class="notice success">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="notice danger"><strong>Action not completed.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="panel"><div class="panel-header"><div><h2>1. Link an existing Pterodactyl account</h2><p>The remote user must already exist. Email addresses must match exactly (case-insensitive). No account will be created.</p></div></div>
@if($users->isEmpty())<p class="helper">No client accounts available.</p>@else
<form method="POST" action="{{ route('pterohub.manual.link') }}">@csrf
<div class="ph-preflight-fields"><label>PteroHub customer<select name="user_id" required>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->email }} ({{ $user->name }})</option>@endforeach</select></label>
<label>Existing Pterodactyl user ID<input name="remote_user_id" type="number" min="1" required placeholder="e.g. 12"></label></div>
<button class="button primary" type="submit">Verify and link existing user</button></form>@endif
@if($links->isNotEmpty())<div class="table-scroll"><table class="data-table"><thead><tr><th>PteroHub customer</th><th>Remote user ID</th><th>Verified email</th></tr></thead><tbody>@foreach($links as $link)<tr><td>{{ $link->customer_email }}</td><td>#{{ $link->remote_user_id }}</td><td>{{ $link->remote_email }}</td></tr>@endforeach</tbody></table></div>@endif
</div>
<div class="panel"><div class="panel-header"><div><h2>2. Review confirmed order item</h2><p>Checks mapping, remote Egg variables, server inventory, node resources and free allocations before the final form is shown.</p></div></div>
@if($items->isEmpty() || $nodes->isEmpty())<p class="helper">You need a confirmed test order and a synchronized node.</p>@else
<form method="POST" action="{{ route('pterohub.manual.review') }}">@csrf
<div class="ph-preflight-fields"><label>Confirmed order item<select name="order_item_id" required>@foreach($items as $item)<option value="{{ $item->id }}">#{{ $item->id }} · {{ $item->reference }} · {{ $item->plan_name }} · {{ $item->customer_email }}</option>@endforeach</select></label>
<label>Target node<select name="node_id" required>@foreach($nodes as $node)<option value="{{ $node->id }}">{{ $node->name }} ({{ $node->fqdn }})</option>@endforeach</select></label></div>
<button class="button primary" type="submit">Review provisioning</button></form>@endif
</div>
<div class="panel"><div class="panel-header"><div><h2>3. Reconcile a previous attempt</h2><p>Remote inventory is checked again before any retry can be authorized. This action never creates a server. If a matching server exists, its owner, Egg and node are verified before linking it.</p></div></div>
@if($reconcileServices->isEmpty())<p class="helper">No service currently requires reconciliation.</p>@else
@foreach($reconcileServices as $service)
<div class="notice"><strong>Service #{{ $service->id }}</strong> · Order item #{{ $service->order_item_id }} · {{ $service->status }}<p class="helper">Expected remote external ID: pterohub-order-item-{{ $service->order_item_id }}</p></div>
<form method="POST" action="{{ route('pterohub.manual.reconcile') }}">@csrf
<input type="hidden" name="service_id" value="{{ $service->id }}">
<label><input type="checkbox" name="acknowledge" value="1" required> I have checked the remote panel and authorize a fresh read-only inventory verification. I understand this can unlock a subsequent, separately confirmed server creation.</label>
<label>Type <strong>RECONCILE {{ $service->id }}</strong> to confirm<input type="text" name="confirmation" required autocomplete="off" placeholder="RECONCILE {{ $service->id }}"></label>
<button type="submit" class="button primary">Verify remote state and reconcile service #{{ $service->id }}</button>
</form>
@endforeach
@endif</div>
<div class="panel"><div class="panel-header"><div><h2>Recent provisioning attempts</h2><p>Any pending or uncertain attempt blocks a second server creation. Never remove these records to bypass a failure.</p></div></div>
@if($attempts->isEmpty())<p class="helper">No provisioning attempts recorded.</p>@else<div class="table-scroll"><table class="data-table"><thead><tr><th>Order item</th><th>Action</th><th>Status</th><th>Remote server</th><th>Created</th></tr></thead><tbody>
@foreach($attempts as $attempt)<tr><td>#{{ $attempt->order_item_id }}</td><td>{{ $attempt->action }}</td><td>{{ $attempt->status }}</td><td>{{ $attempt->remote_server_id ?: 'Not confirmed' }}</td><td>{{ $attempt->created_at }}</td></tr>@endforeach
</tbody></table></div>@endif
</div>
@endsection
