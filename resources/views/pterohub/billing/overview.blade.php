@extends('pterohub.layout')
@section('title','Billing overview')
@section('content')
<div class="page-heading"><div><div class="eyebrow">COMMERCE / BILLING</div><h1>Billing overview</h1><p class="intro">Read-only overview of simulated documents and manual renewal plans. No money moves here.</p></div><a class="button subtle" href="{{ route('pterohub.invoices.index') }}">Invoices</a></div>
<div class="ph-billing-stats">
 <div class="panel"><span>Services</span><strong>{{ $summary['services'] }}</strong></div>
 <div class="panel"><span>Recorded due dates past</span><strong>{{ $summary['overdue'] }}</strong></div>
 <div class="panel"><span>Recorded due within 30 days</span><strong>{{ $summary['due_30'] }}</strong></div>
 <div class="panel"><span>Manual renewal plans</span><strong>{{ $summary['planned'] }}</strong></div>
</div>
<div class="panel"><h2>Simulated document summary</h2><p class="intro">Amounts are test values, not real receivables or fiscal documents.</p><div class="table-scroll"><table class="data-table"><thead><tr><th>Status</th><th>Documents</th><th>Simulated total</th></tr></thead><tbody>
@foreach(['draft','issued','paid','cancelled'] as $status)
@php($group=$invoiceCounts->get($status))
<tr><td>{{ ucfirst($status) }}</td><td>{{ $group->total ?? 0 }}</td><td>€ {{ number_format(($group->amount_minor ?? 0)/100,2,',','.') }}</td></tr>
@endforeach
</tbody></table></div></div>
<div class="panel"><h2>Upcoming manual renewal plans</h2><p class="intro">Planning notes only. They do not change the contractual due date or create a renewal.</p><div class="table-scroll"><table class="data-table"><thead><tr><th>Planned for</th><th>Service</th><th>Customer</th><th>Plan</th></tr></thead><tbody>
@forelse($plans as $plan)<tr><td>{{ $plan->planned_for }}</td><td><a href="{{ route('pterohub.services.show',$plan->hosting_service_id) }}">#{{ $plan->hosting_service_id }}</a></td><td>{{ $plan->customer_email }}</td><td>{{ $plan->plan_name }}</td></tr>
@empty<tr><td colspan="4">No manual renewal plans.</td></tr>@endforelse
</tbody></table></div></div>
<div class="panel"><h2>Services requiring billing review</h2><p class="intro">Includes recorded due dates within 30 days or earlier and manual plans within 30 days. The same service is listed only once.</p><div class="table-scroll"><table class="data-table"><thead><tr><th>Service</th><th>Customer</th><th>Recorded due</th><th>Manual plan</th><th>Local status</th></tr></thead><tbody>
@forelse($services as $service)<tr><td><a href="{{ route('pterohub.services.show',$service->id) }}">#{{ $service->id }} · {{ $service->plan_name }}</a></td><td>{{ $service->customer_email }}</td><td>{{ $service->next_due_at ?? 'Not set' }}</td><td>{{ $service->plan_status === 'planned' ? $service->planned_for : 'None' }}</td><td>{{ ucfirst(str_replace('_',' ',$service->status)) }}</td></tr>
@empty<tr><td colspan="5">No services require review in this window.</td></tr>@endforelse
</tbody></table></div></div>
<div class="notice">No automated charges, invoice generation, service suspension or server provisioning are enabled. Review dates manually before communicating them to customers.</div>
@endsection
