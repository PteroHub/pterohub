@extends('pterohub.layout')
@section('title','Sandbox payments')
@section('content')
<div class="page-header"><div><span class="eyebrow">COMMERCE / TESTING</span><h1>Sandbox payments</h1><p>Verified sandbox evidence only. No real charges, order confirmation, tax invoice, renewal or server operation.</p></div></div>
<section class="card"><h2>Provider configuration</h2><p>Stripe test mode: <strong>{{ $payments->enabled('stripe') ? 'Enabled' : 'Disabled' }}</strong>. PayPal sandbox: <strong>{{ $payments->enabled('paypal') ? 'Enabled' : 'Disabled' }}</strong>.</p><p>Keys must be configured in the server environment. Never paste secrets into this page.</p></section>
<section class="card"><h2>Payment attempts</h2><div class="ph-client-table-wrap"><table><thead><tr><th>ID</th><th>Order</th><th>Customer</th><th>Provider</th><th>Status</th><th>Amount</th><th>Checked</th></tr></thead><tbody>
@forelse($attempts as $a)<tr><td>{{ $a->id }}</td><td>#{{ $a->order_id }} ({{ $a->order_status }})</td><td>{{ $a->email }}</td><td>{{ ucfirst($a->provider) }}</td><td>{{ ucfirst($a->status) }} @if($a->failure_code)<small>{{ $a->failure_code }}</small>@endif</td><td>€{{ number_format($a->amount_minor/100,2) }}</td><td>{{ $a->verified_at ?? 'Not verified' }}</td></tr>
@empty<tr><td colspan="7">No sandbox payments yet.</td></tr>@endforelse
</tbody></table></div>{{ $attempts->links() }}</section>
<section class="card"><h2>Verified webhook log</h2><div class="ph-client-table-wrap"><table><thead><tr><th>Provider</th><th>Event</th><th>Status</th><th>Result</th></tr></thead><tbody>
@forelse($webhooks as $e)<tr><td>{{ $e->provider }}</td><td>{{ $e->event_type }}</td><td>{{ $e->status }}</td><td>{{ $e->failure_code ?? '—' }}</td></tr>
@empty<tr><td colspan="4">No webhook events yet.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
