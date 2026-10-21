@extends('pterohub.layout')
@section('title','Hosting Products')
@section('content')
<div class="page-heading"><div><div class="eyebrow">COMMERCE / CATALOG</div><h1>Hosting Products</h1><p class="intro">Manage server packages, resources, recurring prices and catalog publication.</p></div><a class="button primary" href="{{ route('pterohub.products.create') }}"> Create product</a></div>
<div class="panel"><div class="panel-header"><div><h2>Products <span class="count-badge">{{ $plans->count() }}</span></h2><p>Published products appear in the catalog for draft-order testing only.</p></div></div>
<div class="toolbar"><div class="search-input"><span>⌕</span><input id="ph-products-search" placeholder="Search product or game" aria-label="Search products"></div></div>
<div class="table-scroll"><table class="data-table" id="ph-products-table"><thead><tr><th>Product</th><th>Game / Egg</th><th>Resources</th><th>Monthly</th><th>Sale</th><th>Manage</th></tr></thead><tbody>
@forelse($plans as $plan)
@php($price = ($prices[$plan->id] ?? collect())->firstWhere('billing_months',1))
<tr data-search="{{ strtolower($plan->name.' '.$plan->game_name.' '.$plan->egg_name) }}"><td class="egg-name">{{ $plan->name }}<br><small class="helper">#{{ $plan->id }} @if($plan->slot_limit) · {{ $plan->slot_limit }} slots @endif</small></td><td>{{ $plan->game_name }}<br><small class="helper">{{ $plan->egg_name }}</small></td><td>{{ $plan->memory_mb }} MB / {{ $plan->cpu_percent }}% CPU<br><small class="helper">{{ $plan->disk_mb }} MB disk</small></td><td>{{ $price ? '€'.number_format($price->amount_minor / 100,2,',','.') : '—' }}</td><td><span class="status-pill">{{ $plan->active ? 'Active' : 'Draft' }}</span></td><td><a class="button subtle" href="{{ route('pterohub.products.edit',$plan->id) }}">Edit</a> <a class="button subtle" href="{{ route('pterohub.products.delete.confirm',$plan->id) }}">Delete</a></td></tr>
@empty<tr><td colspan="6" class="empty-state">No products yet. Create your first draft to get started.</td></tr>@endforelse
</tbody></table></div></div>

@endsection
