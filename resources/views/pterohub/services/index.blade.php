@extends('pterohub.layout')
@section('title','Services')
@section('content')
<div class="page-heading"><div><div class="eyebrow">COMMERCE / SERVICES</div><h1>Services</h1><p class="intro">Customer services and Pterodactyl associations. Read-only administration.</p></div></div>
<div class="panel ph-service-admin">
<form method="GET" action="{{ route('pterohub.services.index') }}" class="ph-service-filters">
<label>Search customer or product<input name="q" value="{{ $search }}" maxlength="100" placeholder="Email, product or service ID"></label>
<label>Local status<select name="status"><option value="">All statuses</option>
@foreach(['provisioned','active','suspended','provisioning','provisioning_review','provisioning_retry_ready','terminated'] as $choice)
<option value="{{ $choice }}" @selected($status === $choice)>{{ ucfirst(str_replace('_',' ',$choice)) }}</option>
@endforeach</select></label><button type="submit" class="button primary">Filter</button>
</form>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Service</th><th>Customer</th><th>Product</th><th>Local status</th><th>Node</th><th>Remote ID</th><th></th></tr></thead><tbody>
@forelse($services as $service)
<tr><td>#{{ $service->id }}</td><td>{{ $service->customer_name }}<small class="ph-service-muted">{{ $service->customer_email }}</small></td><td>{{ $service->plan_name }}</td><td><span class="status-pill">{{ ucfirst(str_replace('_',' ', $service->status)) }}</span></td><td>{{ $service->node_name ?? 'Not assigned' }}</td><td>{{ $service->remote_server_id ? '#'.$service->remote_server_id : 'None' }}</td><td><a class="button subtle" href="{{ route('pterohub.services.show',$service->id) }}">View</a></td></tr>
@empty<tr><td colspan="7">No services match these filters.</td></tr>@endforelse
</tbody></table></div><div class="ph-service-pagination">{{ $services->links() }}</div>
</div>
@endsection
