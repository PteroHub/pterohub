@extends('pterohub.client.layout')
@section('title','Support ticket')
@section('content')<div class="ph-client-heading"><span>SUPPORT</span><h1>{{ $ticket->subject }}</h1><p>Status: {{ ucfirst($ticket->status) }}</p></div>
@foreach($messages as $message)<div class="ph-client-card ph-client-message"><p>{{ $message->body }}</p><small>{{ $message->created_at }}</small></div>@endforeach
@if($ticket->status !== 'closed')<div class="ph-client-card"><h2>Reply</h2><form method="POST" action="{{ route('ph.client.tickets.reply',$ticket->id) }}">@csrf
<label>Message<textarea name="body" required rows="5" maxlength="15000"></textarea></label><button type="submit">Send reply</button></form></div>@endif
@endsection
