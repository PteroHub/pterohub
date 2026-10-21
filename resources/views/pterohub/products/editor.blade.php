@extends('pterohub.layout')
@section('title', $plan ? 'Edit Product' : 'New Product')
@section('content')
@if($plan)<div class="ph-editor-delete-bar"><a class="button subtle" href="{{ route('pterohub.products.delete.confirm', $plan->id) }}">Delete product</a></div>@endif
@php($isEdit = (bool)$plan)
<div class="page-heading"><div><div class="eyebrow">CATALOG / <a href="{{ route('pterohub.products.index') }}">PRODUCTS</a></div><h1>{{ $isEdit ? 'Edit hosting product' : 'Create hosting product' }}</h1><p class="intro">Configure the product and its catalog visibility. Payments and server provisioning are disabled.</p></div><a class="button subtle" href="{{ route('pterohub.products.index') }}"> All products</a></div>
<div class="ph-product-tabs" role="tablist" aria-label="Product settings"><button class="selected" type="button" data-phtab="general" role="tab">General</button><button type="button" data-phtab="resources" role="tab">Resources</button><button type="button" data-phtab="pricing" role="tab" {{ $isEdit ? '' : 'disabled' }}>Pricing</button></div>
<form method="POST" action="{{ $isEdit ? route('pterohub.products.update',$plan->id) : route('pterohub.products.store') }}" id="ph-product-form">@csrf
<section class="panel ph-product-panel" data-phpanel="general"><div class="panel-header"><div><h2>General settings</h2><p>Product information and game configuration.</p></div><span class="read-only-chip">{{ $plan && $plan->active ? 'Published' : 'Draft catalog' }}</span></div><div class="form-grid">
<div class="field field-wide"><label for="product-name">Product name</label><input required id="product-name" name="name" maxlength="150" value="{{ old('name',$plan->name ?? '') }}" placeholder="e.g. Call of Duty 4 - Standard"></div>
<div class="field"><label for="product-game">Game</label><select required id="product-game" name="game_id"><option value="">Select a game</option>@foreach($games as $game)<option value="{{ $game->id }}" @selected((string)old('game_id',$plan->game_id ?? '') === (string)$game->id)>{{ $game->name }}</option>@endforeach</select></div>
<div class="field field-wide"><label for="product-egg">Pterodactyl Egg</label><select required id="product-egg" name="pterodactyl_egg_id"><option value="">Select an Egg</option>@foreach($eggs as $egg)<option value="{{ $egg->id }}" data-game="{{ $egg->game_id }}" @selected((string)old('pterodactyl_egg_id',$plan->pterodactyl_egg_id ?? '') === (string)$egg->id)>#{{ $egg->remote_id }} — {{ $egg->name }}</option>@endforeach</select><small class="field-hint">Only Egg assignments matching the selected game can be saved.</small></div>
</div><div class="ph-editor-actions"><button class="button primary" type="button" data-phnext="resources">Next: Resources </button></div></section>
<section class="panel ph-product-panel" data-phpanel="resources" hidden><div class="panel-header"><div><h2>Server resources</h2><p>These values are saved in the hosting plan; no server is created.</p></div></div><div class="form-grid">
@foreach([['memory_mb','RAM (MB)',2048,128],['cpu_percent','CPU (%)',100,1],['disk_mb','Disk (MB)',10240,512],['allocations','Allocations',1,1],['backups','Backups',0,0],['databases','Databases',0,0],['swap_mb','Swap (MB)',0,0]] as $field)
<div class="field"><label for="ph-{{ $field[0] }}">{{ $field[1] }}</label><input type="number" id="ph-{{ $field[0] }}" name="{{ $field[0] }}" value="{{ old($field[0],$plan->{$field[0]} ?? $field[2]) }}" min="{{ $field[3] }}" required></div>
@endforeach
<div class="field"><label for="ph-slot-limit">Optional player slots</label><input type="number" id="ph-slot-limit" name="slot_limit" value="{{ old('slot_limit',$plan->slot_limit ?? '') }}" min="1" max="512" placeholder="Not configured"><small class="field-hint">Informational only. Does not alter Egg startup variables.</small></div>
</div><div class="ph-editor-actions"><button class="button subtle" type="button" data-phnext="general"> General</button><button class="button primary" type="submit">{{ $isEdit ? 'Save product' : 'Create draft product' }}</button></div></section>
</form>
<section class="panel ph-product-panel" data-phpanel="pricing" hidden><div class="panel-header"><div><h2>Pricing periods</h2><p>Amounts in EUR. Disable a period to remove that price.</p></div></div>
@if($isEdit)
<form method="POST" action="{{ route('pterohub.products.prices',$plan->id) }}">@csrf<div class="ph-price-list">
@foreach([1=>'Monthly',3=>'Quarterly',6=>'Semiannual',12=>'Annual'] as $months=>$label)
@php($pr = $prices->get($months))
<div class="ph-price-item"><label class="ph-period"><input type="checkbox" name="periods[]" value="{{ $months }}" @checked((bool)$pr)><strong>{{ $label }}</strong><small>{{ $months }} {{ $months===1?'month':'months' }}</small></label><div class="field"><label>Recurring (€)</label><input type="number" step="0.01" min="0" max="1000000" name="amount[{{ $months }}]" value="{{ old('amount.'.$months,$pr ? number_format($pr->amount_minor/100,2,'.','') : '') }}" placeholder="0.00"></div><div class="field"><label>Setup fee (€)</label><input type="number" step="0.01" min="0" max="1000000" name="setup[{{ $months }}]" value="{{ old('setup.'.$months,$pr ? number_format($pr->setup_minor/100,2,'.','') : '0.00') }}"></div></div>
@endforeach
</div><div class="ph-editor-actions"><button class="button primary" type="submit">Save prices</button></div></form>
@else<p class="helper">Create the draft product first, then return here to set prices.</p>@endif
</section>
@if($isEdit)
<div class="panel" style="margin-top:18px">
  <div class="panel-header"><div><h2>Catalog publication</h2><p>Publish to the public catalog for testing customer draft orders. No payments or provisioning.</p></div><span class="status-pill">{{ $plan->active ? 'Active' : 'Draft' }}</span></div>
  <form method="POST" action="{{ route('pterohub.products.publication', $plan->id) }}">@csrf
    @if(!$plan->active)
      <input type="hidden" name="status" value="active">
      @if(!$publicationGame?->active)<label class="ph-period"><input type="checkbox" name="enable_game" value="1" required> Enable the linked game for the public catalog</label>@endif
      @if(!$publicationEgg?->enabled_for_sale)<label class="ph-period"><input type="checkbox" name="enable_egg" value="1" required> Enable the linked Pterodactyl Egg for catalog visibility</label>@endif
      <p class="helper">At least one EUR billing period must be configured. Enabling the game or Egg can also affect other already-published products that share them.</p>
      <button class="button primary" type="submit">Publish product</button>
    @else
      <input type="hidden" name="status" value="draft">
      <p class="helper">Unpublishing hides this product from new customer orders. Existing draft orders remain intact.</p>
      <button class="button subtle" type="submit">Unpublish product</button>
    @endif
  </form>
</div>
<div class="panel" style="margin-top:18px">
  <div class="panel-header"><div><h2>Delete product</h2><p>Only products without customer history can be permanently deleted. Existing orders and services are protected.</p></div>
    <a class="button subtle" href="{{ route('pterohub.products.delete.confirm', $plan->id) }}">Delete product</a>
  </div>
</div>
@endif
@if($nodes->count()===0)<div class="ph-note">No Pterodactyl nodes have been added yet. No Pterodactyl nodes have been added yet. Catalog publication is available for draft-order testing only; real provisioning remains disabled.</div>@endif

@endsection
