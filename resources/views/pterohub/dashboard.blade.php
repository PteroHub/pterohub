@extends('pterohub.layout')
@section('title','Overview')
@section('content')
<div class="page-heading"><div><p class="eyebrow">CONTROL CENTER / OVERVIEW</p><h1>Welcome back </h1><p class="intro">Manage your games, Pterodactyl Eggs and draft hosting plans.</p></div><span class="read-only-chip">● Catalog configuration only</span></div>

<div class="ph-tab-panel" data-tab-panel="overview" id="overview" role="tabpanel" aria-labelledby="ph-tab-overview"><div class="stats-grid">
 <a href="#eggs" class="stat-card"><div class="stat-top"><span>Imported Eggs</span></div><div class="stat-value">{{ $eggs->count() }}</div><div class="stat-detail">Synced from Pterodactyl</div></a>
 <a href="#games" class="stat-card"><div class="stat-top"><span>Games</span></div><div class="stat-value">{{ $games->count() }}</div><div class="stat-detail">Catalog categories</div></a>
 <a href="#plans" class="stat-card"><div class="stat-top"><span>Hosting plans</span></div><div class="stat-value">{{ $plans->count() }}</div><div class="stat-detail">Inactive until enabled later</div></a>
 <div class="stat-card"><div class="stat-top"><span>Nodes</span></div><div class="stat-value">{{ $nodeCount }}</div><div class="stat-detail">No automatic provisioning</div></div>
</div>

</div>

<div class="ph-tab-panel" id="games" role="tabpanel" aria-labelledby="ph-tab-games" data-tab-panel="games" hidden>
<div class="section-title"><div><h2>Games & catalog</h2><p>Create game entries and choose their engine families.</p></div><span class="section-counter">{{ $games->count() }} games</span></div>
<section class="panel"><div class="panel-header"><div><h3>Add or update a game</h3><p>Games remain unpublished until commerce features are ready.</p></div></div><form class="form-grid game-form" method="POST" action="{{ route('pterohub.admin.game') }}">@csrf<div class="field"><label for="game-name">Game name</label><input id="game-name" name="name" required maxlength="150" placeholder="Call of Duty 4"></div><div class="field"><label for="game-engine">Engine</label><input id="game-engine" name="engine" maxlength="120" placeholder="IW Engine / id Tech 3"></div><div class="field field-wide"><label for="game-description">Description (optional)</label><input id="game-description" name="description" maxlength="1000" placeholder="A short description of this game"></div><div class="form-action"><button class="button primary" type="submit">Save game <span></span></button></div></form></section>

<section class="panel" id="catalog-management">
 <div class="panel-header"><div><h3>Catalog management</h3><p>Edit game metadata and synchronize your existing Pterodactyl Eggs. No product is published automatically.</p></div><span class="count-badge">v0.7</span></div>
 <form class="ph-catalog-actions" method="POST" action="{{ route('pterohub.catalog.sync') }}" onsubmit="return confirm('Synchronize Egg and node inventory from Pterodactyl? Existing associations and sales settings will remain unchanged.')">@csrf<button class="button primary" type="submit"> Synchronize Eggs & nodes</button></form>
 <div class="ph-game-grid">
 @forelse($games as $game)
 <article class="ph-game-card"><h4>{{ $game->name }}</h4><p class="ph-game-id">Game #{{ $game->id }} · Catalog metadata</p>
 <form method="POST" action="{{ route('pterohub.catalog.game.update',$game->id) }}">@csrf
 <div class="ph-game-fields">
 <label>Game name<input name="name" required maxlength="150" value="{{ $game->name }}"></label>
 <label>Engine<input name="engine" maxlength="120" value="{{ $game->engine }}"></label>
 <label class="ph-wide">Description<input name="description" maxlength="1000" value="{{ $game->description }}"></label>
 </div><div class="ph-game-actions"><button class="button subtle" type="submit">Save changes</button></div></form></article>
 @empty <p class="ph-game-empty">No games created yet.</p> @endforelse
 </div>
</section>

</div>

<div class="ph-tab-panel" id="eggs" role="tabpanel" aria-labelledby="ph-tab-eggs" data-tab-panel="eggs" hidden>
<section class="panel"><div class="panel-header"><div><h3>Pterodactyl Eggs</h3><p>Assign each imported Egg to a game. This never enables sales.</p></div><span class="count-badge">{{ $eggs->count() }} imported</span></div><div class="toolbar"><div class="search-input"><span aria-hidden="true">⌕</span><input aria-label="Search Eggs" id="eggSearch" placeholder="Search Eggs by name or ID…" type="search"></div><span class="helper">Saved individually</span></div><div class="table-scroll"><table class="data-table" id="eggTable"><thead><tr><th>Egg ID</th><th>Egg name</th><th>Associated game</th><th>Action</th></tr></thead><tbody>@foreach($eggs as $egg)<tr data-search="{{ strtolower('#'.$egg->remote_id.' '.$egg->name.' '.($egg->game_name ?? '')) }}"><td><span class="id-tag">#{{ $egg->remote_id }}</span></td><td class="egg-name">{{ $egg->name }}</td><td colspan="2"><form class="inline-form" method="POST" action="{{ route('pterohub.admin.assign', $egg->id) }}">@csrf<label class="sr-only" for="egg-{{ $egg->id }}">Associated game for {{ $egg->name }}</label><select id="egg-{{ $egg->id }}" name="game_id"><option value="">Unassigned</option>@foreach($games as $game)<option value="{{ $game->id }}" @selected($egg->game_id == $game->id)>{{ $game->name }}</option>@endforeach</select><button class="button subtle" type="submit">Save</button></form></td></tr>@endforeach</tbody></table></div><p class="table-footer" id="eggCount">Showing {{ $eggs->count() }} imported Eggs</p></section>

</div>

<div class="ph-tab-panel" id="plans" role="tabpanel" aria-labelledby="ph-tab-plans" data-tab-panel="plans" hidden>
<div class="section-title"><div><h2>Hosting plans</h2><p>Set up resources and initial prices for each game.</p></div><span class="section-counter">Draft only</span></div>
<section class="panel"><div class="panel-header"><div><h3>Create a hosting plan</h3><p>Assign an Egg to its game first. Newly created plans remain inactive.</p></div><span class="count-badge neutral">Not for sale</span></div><form class="form-grid plan-form" method="POST" action="{{ route('pterohub.admin.plan') }}">@csrf<div class="field"><label for="plan-name">Plan name</label><input id="plan-name" name="name" placeholder="CoD4 Starter" maxlength="150" required></div><div class="field"><label for="plan-game">Game</label><select id="plan-game" name="game_id" required>@foreach($games as $game)<option value="{{ $game->id }}">{{ $game->name }}</option>@endforeach</select></div><div class="field"><label for="plan-egg">Egg</label><select id="plan-egg" name="pterodactyl_egg_id" required>@foreach($eggs->whereNotNull('game_id') as $egg)<option value="{{ $egg->id }}">#{{ $egg->remote_id }} {{ $egg->name }}</option>@endforeach</select></div><div class="field"><label for="plan-ram">RAM (MB)</label><input id="plan-ram" type="number" name="memory_mb" min="128" value="2048" required></div><div class="field"><label for="plan-disk">Disk (MB)</label><input id="plan-disk" type="number" name="disk_mb" min="512" value="10240" required></div><div class="field"><label for="plan-cpu">CPU (%)</label><input id="plan-cpu" type="number" name="cpu_percent" min="1" value="100" required></div><div class="field"><label for="plan-alloc">Allocations</label><input id="plan-alloc" type="number" name="allocations" min="1" value="1" required></div><div class="field"><label for="plan-backups">Backups</label><input id="plan-backups" type="number" name="backups" min="0" value="0" required></div><div class="field"><label for="plan-dbs">Databases</label><input id="plan-dbs" type="number" name="databases" min="0" value="0" required></div><div class="field"><label for="plan-billing">Billing period</label><select id="plan-billing" name="billing_months"><option value="1">Monthly</option><option value="3">Quarterly</option><option value="6">Semiannual</option><option value="12">Annual</option></select></div><div class="field"><label for="plan-price">Price in euro cents</label><input id="plan-price" type="number" name="amount_minor" min="0" value="500" required><span class="field-hint">500 = €5.00</span></div><div class="form-action"><button type="submit" class="button primary">Create draft plan <span></span></button></div></form></section>
<section class="panel"><div class="panel-header"><div><h3>Existing hosting plans</h3><p>Overview of resources and pricing configured so far.</p></div><span class="count-badge">{{ $plans->count() }} plans</span></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Plan</th><th>Game</th><th>Egg</th><th>RAM</th><th>CPU</th><th>Prices</th><th>Status</th><th>Manage</th></tr></thead><tbody>@forelse($plans as $plan)<tr><td class="egg-name">{{ $plan->name }}</td><td>{{ $plan->game_name }}</td><td>{{ $plan->egg_name }}</td><td>{{ $plan->memory_mb }} MB</td><td>{{ $plan->cpu_percent }}%</td><td>@foreach($prices->where('hosting_plan_id',$plan->id) as $price)<span class="price-row">€{{ number_format($price->amount_minor/100,2) }} / {{ $price->billing_months }} mo</span>@endforeach</td><td><span class="status-pill">{{ $plan->active ? 'Enabled in DB' : 'Draft' }}</span></td><td><a class="button subtle" href="{{ route('pterohub.products.edit',$plan->id) }}">Edit</a> <a class="button subtle" href="{{ route('pterohub.products.delete.confirm',$plan->id) }}">Delete</a></td></tr>@empty<tr><td colspan="8" class="empty-state">No plans yet. Create your first draft above.</td></tr>@endforelse</tbody></table></div></section>

<section class="panel" id="plan-management"><div class="panel-header"><div><h3>Edit draft plans & billing periods</h3><p>All plans remain in their existing publication state. Pricing is stored in euro cents.</p></div><span class="count-badge">1 · 3 · 6 · 12 months</span></div>
@forelse($plans as $plan)
<details class="catalog-plan"><summary><strong>{{ $plan->name }}</strong> <span>{{ $plan->game_name }} · {{ $plan->memory_mb }} MB RAM · {{ $plan->cpu_percent }}% CPU · {{ $plan->active ? 'Existing active flag' : 'Draft' }}</span></summary>
 <form method="POST" action="{{ route('pterohub.catalog.plan.update',$plan->id) }}" class="catalog-plan-form">@csrf
 <label>Plan name<input name="name" maxlength="150" required value="{{ $plan->name }}"></label>
 <label>Game<select name="game_id" required>@foreach($games as $game)<option value="{{ $game->id }}" @selected($game->id == $plan->game_id)>{{ $game->name }}</option>@endforeach</select></label>
 <label>Egg<select name="pterodactyl_egg_id" required>@foreach($eggs as $egg)<option value="{{ $egg->id }}" @selected($egg->id == $plan->pterodactyl_egg_id)>#{{ $egg->remote_id }} {{ $egg->name }}</option>@endforeach</select></label>
 @foreach(['memory_mb'=>'RAM (MB)','disk_mb'=>'Disk (MB)','cpu_percent'=>'CPU (%)','allocations'=>'Allocations','backups'=>'Backups','databases'=>'Databases'] as $field=>$label)<label>{{ $label }}<input type="number" name="{{ $field }}" required min="{{ in_array($field,['backups','databases'])?0:1 }}" value="{{ $plan->$field }}"></label>@endforeach
 <button class="button primary" type="submit">Save plan changes</button></form>
 <form method="POST" action="{{ route('pterohub.catalog.plan.prices',$plan->id) }}" class="catalog-price-form">@csrf<h4>Recurring prices (EUR cents)</h4><div class="catalog-prices">
 @foreach([1=>'Monthly',3=>'Quarterly',6=>'Semiannual',12=>'Annual'] as $months=>$period)
 @php($price=$prices->first(fn($p)=>$p->hosting_plan_id==$plan->id && $p->billing_months==$months && $p->currency==='EUR'))
 <label>{{ $period }}<small>Amount (cents)</small><input type="number" min="0" max="100000000" name="prices[{{ $months }}][amount_minor]" value="{{ $price?->amount_minor }}" placeholder="Not configured"><small>Setup (cents)</small><input type="number" min="0" max="100000000" name="prices[{{ $months }}][setup_minor]" value="{{ $price?->setup_minor ?? 0 }}"></label>
 @endforeach
 </div><button class="button subtle" type="submit">Save prices</button></form>
<div class="ph-editor-delete-bar"><a class="button subtle" href="{{ route('pterohub.products.delete.confirm',$plan->id) }}">Delete hosting plan</a></div>
</details>
@empty<p>No hosting plans created yet.</p>@endforelse
</section>

</div>



@endsection
