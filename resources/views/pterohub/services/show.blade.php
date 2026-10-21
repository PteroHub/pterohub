@extends('pterohub.layout')
@section('title','Service #'.$service->id)
@section('content')
<div class="page-heading"><div><div class="eyebrow">COMMERCE / SERVICES</div><h1>Service #{{ $service->id }}</h1><p class="intro">{{ $service->plan_name }} · {{ $service->customer_email }}</p></div><a class="button subtle" href="{{ route('pterohub.services.index') }}">All services</a></div>
<div class="panel ph-service-admin"><h2>Service information</h2>
<div class="ph-service-info">
<div><span>Local status</span><strong>{{ ucfirst(str_replace('_',' ', $service->status)) }}</strong></div>
<div><span>Customer</span><strong>{{ $service->customer_name }}<small class="ph-service-muted">{{ $service->customer_email }}</small></strong></div>
<div><span>Product</span><strong>{{ $service->plan_name }}</strong></div>
<div><span>Node</span><strong>{{ $service->node_name ?? 'Not assigned' }}</strong></div>
<div><span>Remote server ID</span><strong>{{ $service->remote_server_id ? '#'.$service->remote_server_id : 'Not linked' }}</strong></div>
<div><span>Provisioned</span><strong>{{ $service->provisioned_at ?? 'Not recorded' }}</strong></div>
<div><span>Next due</span><strong>{{ $service->next_due_at ?? 'Not scheduled' }}</strong></div>
<div><span>Order</span><strong>@if($service->order_id)<a href="{{ route('pterohub.orders.show',$service->order_id) }}">{{ $service->order_reference }}</a>@else Not linked @endif</strong></div>
</div></div>
<div class="panel ph-service-admin"><div class="ph-service-head"><div><h2>Remote identity verification</h2><p class="intro">Checks the Pterodactyl Application API. No server commands or local lifecycle changes.</p></div>
<form method="POST" action="{{ route('pterohub.services.verify',$service->id) }}">@csrf<button class="button primary" type="submit">Verify now</button></form></div>
@if($remote['available'])
<div class="notice success" role="status">Remote server identity verified.</div>
<div class="ph-service-info"><div><span>Installation</span><strong>{{ $remote['installation'] ?? 'Not reported' }}</strong></div><div><span>Suspension</span><strong>{{ $remote['suspended'] === null ? 'Not reported' : ($remote['suspended'] ? 'Suspended' : 'Not suspended') }}</strong></div><div><span>Last checked</span><strong>{{ $remote['checked_at'] ?? 'Not reported' }}</strong></div><div><span>Panel</span><strong>@if($remote['panel_url'])<a href="{{ $remote['panel_url'] }}" target="_blank" rel="noopener noreferrer">Open server in Pterodactyl</a>@else No verified link @endif</strong></div></div>
@else<div class="notice danger" role="status">Remote identity not verified. The server may be unavailable, missing or inconsistent. No changes have been made.</div>@endif
<p class="ph-service-note">This is a read-only identity and installation snapshot, not a live Wings status. Local “Provisioned” does not mean the server is online.</p>
</div>
<div class="panel ph-service-admin">
<div class="ph-service-head"><div><h2>Manual remote status check</h2><p class="intro">Checks remote identity, installation and suspension. Commercial status and server power are separate.</p></div>
<form method="POST" action="{{ route('pterohub.services.sync',$service->id) }}">@csrf<button class="button primary" type="submit">Check remote status</button></form></div>
@if($latestSync)
<div class="ph-service-info">
<div><span>Last diagnostic result</span><strong>{{ ucfirst(str_replace('_',' ',$latestSync->result)) }}</strong></div>
<div><span>Checked at</span><strong>{{ $latestSync->created_at }}</strong></div>
<div><span>Installation</span><strong>{{ $latestSync->result === 'verified' ? ($latestSync->installation ?? 'Not reported') : 'Unavailable' }}</strong></div>
<div><span>Remote suspension</span><strong>{{ $latestSync->result === 'verified' ? (is_null($latestSync->remote_suspended) ? 'Not reported' : ($latestSync->remote_suspended ? 'Suspended' : 'Not suspended')) : 'Unavailable' }}</strong></div>
</div>
@if($latestSync->result === 'remote_missing')<div class="notice danger">Pterodactyl returned 404 for this server. Investigate before taking any action. No service changes were made.</div>
@elseif($latestSync->result === 'identity_mismatch')<div class="notice danger">Remote identity mismatch. The server has not been relinked.</div>
@elseif($latestSync->result !== 'verified')<div class="notice">Remote state cannot be confirmed. A temporary API issue is not proof that the server is missing.</div>@endif
@else<p class="ph-service-note">No explicit remote status check has been recorded yet.</p>@endif
<p class="ph-service-note">The Pterodactyl Application API does not provide live Wings power state here. Provisioned is a local service lifecycle state, not an online indicator. No background sync is enabled.</p>
</div>
<div class="panel ph-service-admin"><div class="ph-service-head"><div><h2>Renewal simulation</h2><p class="intro">Estimate only, based on the original order item. No automatic renewal or billing.</p></div>
<form method="POST" action="{{ route('pterohub.services.renewal.preview',$service->id) }}">@csrf<button class="button subtle" type="submit">Record dry-run</button></form></div>
@if($renewal['available'])
<div class="ph-service-info"><div><span>Billing interval</span><strong>{{ $renewal['months'] }} months</strong></div>
<div><span>Original item amount</span><strong>{{ number_format($renewal['amount_minor']/100,2,',','.') }} {{ $renewal['currency'] }}</strong></div>
<div><span>Illustrative date</span><strong>{{ $renewal['example_date'] }}</strong></div>
<div><span>Actual due date</span><strong>{{ $service->next_due_at ?? 'Not scheduled' }}</strong></div></div>
<p class="ph-service-note">Illustrative date is calculated from {{ $renewal['has_due_date'] ? 'the recorded next due date' : 'the provisioned or created date' }} and the original billing interval. It is NOT a scheduled charge or a promise of renewal. Taxes, discounts, current pricing and payment status are not recalculated.</p>
@else<p class="ph-service-note">No reliable order-item billing interval is available for this service.</p>@endif
</div>
<div class="panel ph-service-admin"><h2>Manual renewal planning</h2>
<p class="intro">Administrative planning only. Does not change next_due_at, invoice status, payment status or the game server.</p>
@if($renewalPlan)
<div class="ph-service-info"><div><span>Plan status</span><strong>{{ ucfirst($renewalPlan->status) }}</strong></div><div><span>Planned date</span><strong>{{ $renewalPlan->planned_for }}</strong></div><div><span>Note</span><strong>{{ $renewalPlan->note ?: 'None' }}</strong></div></div>
@endif
<form method="POST" action="{{ route('pterohub.renewals.store',$service->id) }}" class="ph-renewal-form">@csrf
<label>Planned review date <input type="date" name="planned_for" required min="{{ today()->toDateString() }}" max="{{ today()->addYears(2)->toDateString() }}" value="{{ old('planned_for',($renewalPlan && $renewalPlan->status === 'planned') ? $renewalPlan->planned_for : '') }}"></label>
<label>Internal note <textarea name="note" rows="2" maxlength="500" placeholder="Optional internal planning note">{{ old('note',$renewalPlan->note ?? '') }}</textarea></label>
<button class="button primary" type="submit">Save manual plan</button></form>
@if($renewalPlan && $renewalPlan->status === 'planned')<form method="POST" action="{{ route('pterohub.renewals.cancel',$service->id) }}" class="ph-renewal-cancel">@csrf<input type="hidden" name="confirmation" value="CANCEL"><button class="button subtle" type="submit">Cancel manual plan</button></form>@endif
<div class="table-scroll"><table class="data-table"><thead><tr><th>Recorded at</th><th>Action</th><th>Planned date</th></tr></thead><tbody>
@forelse($renewalPlanEvents as $event)<tr><td>{{ $event->created_at }}</td><td>{{ str_replace('_',' ',$event->action) }}</td><td>{{ $event->planned_for ?? 'None' }}</td></tr>
@empty<tr><td colspan="3">No planning history yet.</td></tr>@endforelse
</tbody></table></div></div>
<div class="panel ph-service-admin"><h2>Service event history</h2><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Administrator</th><th>Action</th><th>Result</th></tr></thead><tbody>
@forelse($events as $event)<tr><td>{{ $event->created_at }}</td><td>{{ $event->actor_name ?? 'Unknown' }}</td><td>{{ str_replace('_',' ',$event->event_type) }}</td><td>{{ str_replace('_',' ',$event->result) }}</td></tr>
@empty<tr><td colspan="4">No lifecycle events recorded yet.</td></tr>@endforelse
</tbody></table></div></div>
<div class="panel ph-service-admin"><h2>Verification history</h2><div class="table-scroll"><table class="data-table"><thead><tr><th>Checked at</th><th>Administrator</th><th>Result</th><th>Remote ID</th></tr></thead><tbody>
@forelse($checks as $check)<tr><td>{{ $check->created_at }}</td><td>{{ $check->actor_name ?? 'Unknown' }}</td><td>{{ str_replace('_',' ', $check->result) }}</td><td>{{ $check->remote_server_id ? '#'.$check->remote_server_id : 'None' }}</td></tr>
@empty<tr><td colspan="4">No manual verification recorded yet.</td></tr>@endforelse
</tbody></table></div></div>
<div class="panel ph-service-admin"><h2>Provisioning attempt history</h2><div class="table-scroll"><table class="data-table"><thead><tr><th>Attempt</th><th>Action</th><th>Status</th><th>Created</th></tr></thead><tbody>
@forelse($attempts as $attempt)<tr><td>#{{ $attempt->id }}</td><td>{{ $attempt->action }}</td><td>{{ $attempt->status }}</td><td>{{ $attempt->created_at }}</td></tr>
@empty<tr><td colspan="4">No provisioning attempts recorded.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
