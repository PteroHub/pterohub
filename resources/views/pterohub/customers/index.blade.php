@extends('pterohub.layout')
@section('title','Customers')
@section('content')

<div class="page-heading"><div><div class="eyebrow">BILLING / CUSTOMERS</div><h1>Customers</h1><p class="intro">Existing PteroHub accounts and billing profile setup. No automated charges.</p></div><span class="status-pill">Admin only</span></div>
<div class="panel customer-panel"><div class="panel-header"><div><h2>Customer directory</h2><p>Search by name or email. Accounts are managed independently of Pterodactyl.</p></div></div>
<form method="GET" action="{{ route('pterohub.customers.index') }}" class="customer-search"><input class="customer-control" name="q" value="{{ $term }}" placeholder="Customer name or email" aria-label="Customer search"><button type="submit" class="button primary">Search</button></form>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Customer</th><th>Email</th><th>Company</th><th>Created</th><th></th></tr></thead><tbody>
@forelse($customers as $customer)<tr><td><strong>{{ $customer->name }}</strong><br><small class="helper">#{{ $customer->id }}</small></td><td>{{ $customer->email }}</td><td>{{ $customer->company ?: '—' }}</td><td>{{ $customer->created_at }}</td><td><a class="button subtle" href="{{ route('pterohub.customers.show',$customer->id) }}">View </a></td></tr>
@empty<tr><td colspan="5">No customers matched.</td></tr>@endforelse</tbody></table></div>
<div style="margin-top:16px">{{ $customers->links() }}</div></div>
@endsection
