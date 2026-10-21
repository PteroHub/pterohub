@extends('pterohub.layout')
@section('title','Extensions')
@section('content')
<div class="page-heading"><div><div class="eyebrow">PLATFORM / MODULARITY</div><h1>Extensions</h1><p class="intro">Manage registered extension metadata and plan future integrations.</p></div><span class="status-pill">Execution disabled</span></div>
@if(session('success'))<div class="notice">{{ session('success') }}</div>@endif
@if($errors->any())<div class="notice danger" role="alert">{{ $errors->first() }}</div>@endif
<div class="panel"><div class="panel-header"><div><h2>Registered extensions <span class="count-badge">{{ $extensions->count() }}</span></h2><p>Registration only: no external PHP, payment plugin or theme bundle is executed.</p></div></div>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Extension</th><th>Type</th><th>Version</th><th>Status</th><th>Manage</th></tr></thead><tbody>
@forelse($extensions as $e)
<tr><td><strong>{{ $e->name }}</strong><br><small class="helper">{{ $e->slug }} @if($e->provider) · {{ $e->provider }} @endif</small></td><td>{{ ucfirst($e->type) }}</td><td>{{ $e->version }}</td><td><span class="status-pill">Disabled / Registered</span></td><td><details><summary style="cursor:pointer;color:#7549ea;font-weight:650">Edit metadata</summary>
<form method="POST" action="{{ route('pterohub.extensions.update',$e->id) }}" style="margin-top:10px">@csrf<label for="manifest-{{ $e->id }}">Manifest JSON</label><textarea id="manifest-{{ $e->id }}" name="manifest" rows="9" style="width:100%;min-width:300px;margin:7px 0;border:1px solid #dbe0eb;border-radius:12px;padding:12px;font-family:monospace">{{ json_encode(json_decode($e->manifest, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea><button class="button primary" type="submit">Save metadata</button></form>
<form method="POST" action="{{ route('pterohub.extensions.remove',$e->id) }}" style="margin-top:10px" onsubmit="return confirm('Remove this manifest registration?');">@csrf<button class="button" type="submit">Remove registration</button></form>
</details></td></tr>
@empty<tr><td colspan="5">No registered extensions yet.</td></tr>@endforelse
</tbody></table></div></div>
<div class="panel" style="margin-top:22px"><div class="panel-header"><div><h2>Register an extension manifest</h2><p>Only approved metadata keys: slug, name, version, type, provider, description. Never paste credentials.</p></div></div>
<form method="POST" action="{{ route('pterohub.extensions.register') }}">@csrf
<label for="ph-manifest"><strong>Manifest JSON</strong></label>
<textarea id="ph-manifest" name="manifest" rows="9" style="width:100%;margin-top:12px;border:1px solid #dbe0eb;border-radius:12px;padding:14px;background:#fafbff;font-family:monospace" required>{{ old('manifest', '{
  "slug": "sample-theme",
  "name": "Sample Theme",
  "version": "1.0.0",
  "type": "theme",
  "provider": "PteroHub",
  "description": "Metadata-only sample"
}') }}</textarea>
<div style="margin-top:14px"><button type="submit" class="button primary">Register disabled extension</button></div></form></div>
<div class="notice" style="margin-top:20px">This is an extension <strong>registry</strong>, not a plugin runtime. Payment processing, themes, package installation, code execution and activation are not implemented. Future payment plugins will require signed integrations, verified webhook handling, credential isolation and idempotency.</div>
@endsection
