@extends('pterohub.layout')
@section('title', 'Delete hosting product')
@section('content')
<div class="page-heading"><div><div class="eyebrow">CATALOG / PRODUCTS</div><h1>Delete hosting product</h1><p class="intro">Review this action before permanently removing a hosting plan or product. An active plan can be removed if it has no customer history.</p></div><a class="button subtle" href="{{ route('pterohub.products.edit', $plan->id) }}">Cancel</a></div>
<div class="panel">
  <div class="panel-header"><div><h2>{{ $plan->name }}</h2><p>Product #{{ $plan->id }}</p></div><span class="status-pill">{{ $plan->active ? 'Active' : 'Draft' }}</span></div>
  @if($orderCount || $serviceCount)
    <div class="notice danger" role="alert">Deletion is blocked. This product has order or service history. Existing customer records must be preserved.</div>
    <p class="helper">Orders: {{ $orderCount }} · Services: {{ $serviceCount }}</p>
    <a class="button subtle" href="{{ route('pterohub.products.index') }}">All products</a>
  @else
    <p>This permanently removes the hosting plan or product and its configured prices and node assignments. This cannot be undone.</p>
    <form method="POST" action="{{ route('pterohub.products.destroy', $plan->id) }}">
      @csrf
      @method('DELETE')
      <div class="field"><label for="ph-delete-confirm">Type DELETE to confirm</label><input id="ph-delete-confirm" name="confirmation" type="text" autocomplete="off" required pattern="DELETE" placeholder="DELETE"></div>
      <div class="ph-editor-actions"><a class="button subtle" href="{{ route('pterohub.products.edit', $plan->id) }}">Keep product</a><button class="button primary" type="submit">Permanently delete product</button></div>
    </form>
  @endif
</div>
@endsection
