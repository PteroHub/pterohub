<section class="panel" id="catalog-management">
 <div class="panel-header"><div><h3>Catalog management</h3><p>Edit game metadata and synchronize your existing Pterodactyl Eggs. No product is published automatically.</p></div><span class="count-badge">v0.7</span></div>
 <form method="POST" action="{{ route('pterohub.catalog.sync') }}" onsubmit="return confirm('Synchronize Egg and node inventory from Pterodactyl? Existing associations and sales settings will remain unchanged.')">@csrf<button class="button primary" type="submit"> Synchronize Eggs & nodes</button></form>
 <div class="table-scroll"><table class="data-table"><thead><tr><th>Game</th><th>Engine</th><th>Description</th><th>Action</th></tr></thead><tbody>
 @forelse($games as $game)
 <tr><td colspan="4"><form class="catalog-edit-row" method="POST" action="{{ route('pterohub.catalog.game.update',$game->id) }}">@csrf<input name="name" aria-label="Game name" required maxlength="150" value="{{ $game->name }}"><input name="engine" aria-label="Engine" maxlength="120" value="{{ $game->engine }}"><input name="description" aria-label="Description" maxlength="1000" value="{{ $game->description }}"><button class="button subtle" type="submit">Update</button></form></td></tr>
 @empty<tr><td colspan="4">No games created yet. Use the form above.</td></tr>@endforelse
 </tbody></table></div>
</section>
<section class="panel" id="plan-management"><div class="panel-header"><div><h3>Edit draft plans & billing periods</h3><p>Publication is managed from Hosting Products. Pricing is stored in euro cents.</p></div><span class="count-badge">1 · 3 · 6 · 12 months</span></div>
@forelse($plans as $plan)
<details class="catalog-plan"><summary><strong>{{ $plan->name }}</strong> <span>{{ $plan->game_name }} · {{ $plan->memory_mb }} MB RAM · {{ $plan->cpu_percent }}% CPU · {{ $plan->active ? 'Active' : 'Draft' }}</span></summary>
 <form method="POST" action="{{ route('pterohub.catalog.plan.update',$plan->id) }}" class="catalog-plan-form">@csrf
 <label>Plan name<input name="name" maxlength="150" required value="{{ $plan->name }}"></label>
 <label>Game<select name="game_id" required>@foreach($games as $game)<option value="{{ $game->id }}" @selected($game->id == $plan->game_id)>{{ $game->name }}</option>@endforeach</select></label>
 <label>Egg<select name="pterodactyl_egg_id" required>@foreach($eggs as $egg)<option value="{{ $egg->id }}" @selected($egg->id == $plan->pterodactyl_egg_id)>#{{ $egg->remote_id }} {{ $egg->name }}</option>@endforeach</select></label>
 @foreach(['memory_mb'=>'RAM (MB)','disk_mb'=>'Disk (MB)','cpu_percent'=>'CPU (%)','allocations'=>'Allocations','backups'=>'Backups','databases'=>'Databases'] as $field=>$label)<label>{{ $label }}<input type="number" name="{{ $field }}" required min="{{ in_array($field,['backups','databases'])?0:1 }}" value="{{ $plan->$field }}"></label>@endforeach
 <button class="button primary" type="submit">Save plan changes</button> <a class="button subtle" href="{{ route('pterohub.products.edit', $plan->id) }}">Manage publication</a></form>
 <form method="POST" action="{{ route('pterohub.catalog.plan.prices',$plan->id) }}" class="catalog-price-form">@csrf<h4>Recurring prices (EUR cents)</h4><div class="catalog-prices">
 @foreach([1=>'Monthly',3=>'Quarterly',6=>'Semiannual',12=>'Annual'] as $months=>$period)
 @php($price=$prices->first(fn($p)=>$p->hosting_plan_id==$plan->id && $p->billing_months==$months && $p->currency==='EUR'))
 <label>{{ $period }}<small>Amount (cents)</small><input type="number" min="0" max="100000000" name="prices[{{ $months }}][amount_minor]" value="{{ $price?->amount_minor }}" placeholder="Not configured"><small>Setup (cents)</small><input type="number" min="0" max="100000000" name="prices[{{ $months }}][setup_minor]" value="{{ $price?->setup_minor ?? 0 }}"></label>
 @endforeach
 </div><button class="button subtle" type="submit">Save prices</button></form>
</details>
@empty<p>No hosting plans created yet.</p>@endforelse
</section>
