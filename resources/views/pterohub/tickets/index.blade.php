@extends('pterohub.layout')
@section('title','Support Tickets')
@section('content')
<div class="ph18">
<div class="ph18-head"><div><div class="ph18-kicker">CUSTOMER CARE / SUPPORT</div><h1>Support Tickets</h1><p>Manage support conversations, internal notes and ticket status.</p></div><a class="ph18-btn primary" href="{{ route('pterohub.tickets.create') }}"> New ticket</a></div>
@if(session('ok'))<div class="ph18-alert">{{ session('ok') }}</div>@endif
<section class="ph18-card"><div class="ph18-cardhead"><h2>Ticket directory</h2><p>Customer communications are handled by the PteroHub notification system.</p></div>
<form class="ph18-filters" method="GET"><label class="ph18-field">Search<input name="q" value="{{ request('q') }}" placeholder="Subject or customer email"></label><label class="ph18-field">Status<select name="status"><option value="">All statuses</option><option value="open" @selected(request('status')==='open')>Open</option><option value="closed" @selected(request('status')==='closed')>Closed</option></select></label><button class="ph18-btn primary" type="submit">Filter</button></form>
<div class="ph18-tablewrap"><table class="ph18-table"><thead><tr><th>Ticket</th><th>Customer</th><th>Department</th><th>Priority</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody>
@forelse($tickets as $ticket)
<tr><td><strong>#{{ $ticket->id }} · {{ $ticket->subject }}</strong></td><td>{{ $ticket->customer_name }}<small>{{ $ticket->customer_email }}</small></td><td>{{ ucfirst($ticket->department) }}</td><td>{{ ucfirst($ticket->priority) }}</td><td><span class="ph18-pill">{{ ucfirst($ticket->status) }}</span></td><td>{{ $ticket->created_at }}</td><td><a class="ph18-btn soft" href="{{ route('pterohub.tickets.show',$ticket->id) }}">View </a></td></tr>
@empty<tr><td colspan="7">No tickets found.</td></tr>@endforelse
</tbody></table></div><div class="ph18-pages">{{ $tickets->links() }}</div></section></div>
@endsection
