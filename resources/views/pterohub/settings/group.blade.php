@extends('pterohub.layout')
@section('title', $definition['title'])
@section('content')
<div class="ph-settings">
  <div class="ph-settings-head"><div class="eyebrow">SYSTEM / SETTINGS</div><h1>{{ $definition['title'] }}</h1><p>{{ $definition['description'] }}</p><a class="ph-settings-back" href="{{ route('pterohub.settings.index') }}">All settings</a></div>
  @if ($group === 'general')
    <section class="ph-settings-panel"><h2>Branding</h2><p>Manage your company identity, logo and public contact information.</p><a class="button subtle" href="{{ route('pterohub.brand.edit') }}">Open branding settings</a></section>
  @elseif ($group === 'pterodactyl')
    <section class="ph-settings-panel"><p>Use an Application API key with the minimum required read permissions. The secret is encrypted in the database. A saved key takes precedence over legacy .env values. Leave the key blank to keep the existing key.</p><p>Saving credentials does not create, modify or delete servers.</p><a class="button subtle" href="{{ route('pterohub.pterodactyl.index') }}">Open connection diagnostics</a></section>
  @endif
  <section class="ph-settings-panel">
    <form method="POST" action="{{ route('pterohub.settings.save', $group) }}">@csrf
      <div class="ph-settings-fields">
      @foreach ($definition['fields'] as $key => $field)
        <label class="ph-settings-field">
          <span>{{ $field['label'] }}</span>
          @if ($field['type'] === 'select')
            <select name="{{ $key }}">
              @foreach ($field['options'] as $option => $label)
                <option value="{{ $option }}" @selected(old($key, $values[$key]) === $option)>{{ $label }}</option>
              @endforeach
            </select>
          @else
            <input name="{{ $key }}" type="{{ $field['type'] === 'secret' ? 'password' : $field['type'] }}" maxlength="2048" autocomplete="off" value="{{ $field['type'] === 'secret' ? '' : old($key, $values[$key]) }}" placeholder="{{ $field['type'] === 'secret' ? ($field['configured'] ? 'Saved — leave blank to keep' : 'Not configured') : '' }}">
          @endif
          @if ($field['type'] === 'secret') <small>Encrypted at rest. Leave blank to preserve the existing secret.</small> @endif
          @error($key)<small class="ph-settings-error">{{ $message }}</small>@enderror
        </label>
      @endforeach
      </div>
      <div class="ph-settings-actions"><button class="button primary" type="submit">Save settings</button><a class="button subtle" href="{{ route('pterohub.settings.index') }}">Cancel</a></div>
    </form>
  </section>
</div>
@endsection
