@extends('pterohub.layout')
@section('title','Notification Center')
@section('content')
<div class="ph18 ph19 ph110">
 <div class="ph18-head"><div><div class="ph18-kicker">SYSTEM / NOTIFICATIONS</div><h1>Notification Center</h1><p>Choose which future events send email. All new event notifications are disabled until enabled here.</p></div><span class="ph18-pill">Admin only</span></div>
 <p class="ph19-note">Before enabling, confirm SMTP in <a href="{{ route('pterohub.email.index') }}">Email Settings</a>. Verification and password reset emails use Laravel's existing authentication notifications and are not controlled by these switches.</p>
 <section class="ph18-card"><div class="ph18-cardhead"><h2>Automatic notifications</h2><p>Commercial events are descriptive only; no payment, invoice issuance or provisioning is triggered by an email.</p></div>
 <form method="POST" action="{{ route('pterohub.notifications.save') }}" class="ph110-form">@csrf
 @foreach(\App\Services\PterohubNotifications::EVENTS as $key=>$label)
 <div class="ph110-setting"><label><input type="checkbox" name="events[]" value="{{ $key }}" @checked($settings[$key]??false)> <strong>{{ $label }}</strong></label><a href="{{ route('pterohub.notifications.preview',['type'=>$key]) }}" target="_blank" rel="noopener">Preview</a></div>
 @endforeach
 <div class="ph18-actions"><button type="submit" class="ph18-btn primary">Save notification preferences</button></div></form>
 </section>
 <section class="ph18-card ph111-account"><div class="ph18-cardhead"><h2>Send HTML test notification</h2><p>Test the notification template with an address you control. Does not change customer email or generate any order.</p></div>
 <form method="POST" action="{{ route('pterohub.notifications.test') }}">@csrf
 <label>Test recipient<input type="email" name="recipient" required maxlength="254" placeholder="you@example.com" value="{{ old('recipient') }}"></label>
 @error('recipient')<p class="ph-field-error">{{ $message }}</p>@enderror
 <button type="submit" class="ph18-btn primary">Send HTML notification test</button></form></section>
 <section class="ph18-card"><div class="ph18-cardhead"><h2>Delivery history</h2><p>One record per event and recipient. Failed messages can be retried manually up to three attempts.</p></div>
 <form method="GET" class="ph110-filters"><select name="status"><option value="">All statuses</option>@foreach(['sent','failed','pending','sending'] as $st)<option value="{{ $st }}" @selected(request('status')===$st)>{{ ucfirst($st) }}</option>@endforeach</select><select name="event_type"><option value="">All events</option><option value="admin_test" @selected(request('event_type')==='admin_test')>HTML test</option>@foreach(\App\Services\PterohubNotifications::EVENTS as $key=>$label)<option value="{{ $key }}" @selected(request('event_type')===$key)>{{ $label }}</option>@endforeach</select><button type="submit" class="ph18-btn">Filter</button></form>
 <div class="ph110-table-wrap"><table class="ph110-table"><thead><tr><th>Date</th><th>Event</th><th>Recipient</th><th>Status</th><th>Attempts</th><th>Action</th></tr></thead><tbody>
 @forelse($deliveries as $delivery)
 <tr><td>{{ $delivery->created_at }}</td><td>{{ \App\Services\PterohubNotifications::EVENTS[$delivery->event_type]??$delivery->event_type }}<small>{{ $delivery->event_key }}</small></td><td>{{ $delivery->recipient }}</td><td><strong>{{ ucfirst($delivery->status) }}</strong>@if($delivery->failure_class)<small>{{ $delivery->failure_class }}</small>@endif</td><td>{{ $delivery->attempts }}</td><td>@if($delivery->status==='failed' && $delivery->attempts<3 && $delivery->event_type!=='admin_test')<form method="POST" action="{{ route('pterohub.notifications.retry',$delivery->id) }}">@csrf<button type="submit" class="ph18-btn">Retry</button></form>@else<span>—</span>@endif</td></tr>
 @empty<tr><td colspan="6">No notifications have been generated yet.</td></tr>@endforelse
 </tbody></table></div><div class="ph110-pagination">{{ $deliveries->links() }}</div></section>
</div>
@endsection
