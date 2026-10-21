@extends('pterohub.client.layout')
@section('title',ucfirst($kind))
@section('content')<div class="ph-client-heading"><span>CLIENT AREA</span><h1>{{ ucfirst($kind) }}</h1></div>
@if($kind==='invoices')<div class="ph-client-notice">Invoice records here are test documents, not fiscal invoices. Paid status is simulated.</div>@endif
@if($kind==='tickets')<p><a class="ph-client-button" href="{{ route('ph.client.tickets.create') }}">New support ticket</a></p>@endif
<div class="ph-client-card ph-responsive-records"><div class="ph-client-table-wrap"><table><thead><tr><th>Reference</th><th>Details</th><th>Status</th><th>Created</th></tr></thead><tbody>
@forelse($rows as $row)
<tr class="ph-record-row"><td data-label="Reference">
@if($kind==='orders')<a href="{{ route('ph.client.orders.show',$row->id) }}">{{ $row->reference }}</a>
@elseif($kind==='tickets')<a href="{{ route('ph.client.tickets.show',$row->id) }}">#{{ $row->id }}</a>
@elseif($kind==='services')<a href="{{ route('ph.client.services.show',$row->id) }}">#{{ $row->id }}</a>
@elseif($kind==='invoices')<a href="{{ route('ph.client.invoices.show',$row->id) }}">{{ $row->number ?: '#'.$row->id }}</a>
@else #{{ $row->id }} @endif
</td><td data-label="Details">
@if($kind==='orders')€{{ number_format($row->total_minor/100,2) }}
@elseif($kind==='services')<a href="{{ route('ph.client.services.show',$row->id) }}">{{ $row->plan_name }}</a>@if($row->next_due_at)<small class="ph-service-subline">Next due: {{ \Illuminate\Support\Carbon::parse($row->next_due_at)->format('M j, Y') }}</small>@endif
@elseif($kind==='invoices')€{{ number_format($row->total_minor/100,2) }}
@else{{ $row->subject }}@endif
</td><td data-label="Status">{{ ucfirst($row->status) }}</td><td data-label="Created">{{ $row->created_at }}</td></tr>
@empty<tr class="ph-record-empty-row"><td colspan="4"><div class="ph-record-empty"><strong>No {{ $kind }} to display</strong><p>@if($kind==='services')No hosting services yet. Draft orders do not activate servers.@elseif($kind==='orders')Explore the hosting catalog and save configurations to your cart.@elseif($kind==='invoices')No test billing documents are available yet.@else You have no support requests yet.@endif</p>@if($kind==='services' || $kind==='orders')<a href="{{ route('ph.client.catalog') }}">Explore hosting plans</a>@elseif($kind==='tickets')<a href="{{ route('ph.client.tickets.create') }}">Create support ticket</a>@endif</div></td></tr>@endforelse
</tbody></table></div>{{ $rows->links() }}</div>@endsection
