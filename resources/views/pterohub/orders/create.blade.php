@extends('pterohub.layout')
@section('title','New Draft Order')
@section('content')
<div class="orders-ui">
  <div class="page-heading">
    <div><div class="eyebrow">COMMERCE / ORDERS</div><h1>Create draft order</h1><p class="intro">Create an administrative draft using an existing EUR price. No charges or server activation.</p></div>
    <a class="button subtle" href="{{ route('pterohub.orders.index') }}"> Orders</a>
  </div>
  <div class="panel orders-form-panel">
    <div class="panel-header"><div><h2>Draft information</h2><p>Choose the customer, hosting product and billing cycle.</p></div><span class="orders-step">STEP 1 OF 1</span></div>
    @if($errors->any())<div class="notice danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('pterohub.orders.store') }}" class="orders-form">
      @csrf
      <div class="orders-field"><label for="user_id">Customer <span aria-hidden="true">*</span></label>
        <select id="user_id" name="user_id" required><option value="">Select customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('user_id')==$customer->id)>{{ $customer->name }} — {{ $customer->email }}</option>@endforeach</select>
        <p class="orders-help">Choose an existing PteroHub customer account.</p>
      </div>
      <div class="orders-field"><label for="hosting_plan_id">Hosting product <span aria-hidden="true">*</span></label>
        <select id="hosting_plan_id" name="hosting_plan_id" required><option value="">Select hosting product</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected(old('hosting_plan_id')==$plan->id)>{{ $plan->game }} — {{ $plan->name }}</option>@endforeach</select>
        <p class="orders-help">Only products already configured in your catalog can be selected.</p>
      </div>
      <div class="orders-field"><label for="billing_months">Billing period <span aria-hidden="true">*</span></label>
        <select id="billing_months" name="billing_months" required>
          <option value="1" @selected(old('billing_months','1')=='1')>Monthly</option>
          <option value="3" @selected(old('billing_months')=='3')>Quarterly</option>
          <option value="6" @selected(old('billing_months')=='6')>Semiannual</option>
          <option value="12" @selected(old('billing_months')=='12')>Annual</option>
        </select><p class="orders-help">An existing EUR price is required for the selected billing period.</p>
      </div>
      <div class="orders-form-footer"><p><strong>Draft only.</strong> No invoice, payment or server is created.</p><button class="button primary" type="submit">Create draft order <span aria-hidden="true"></span></button></div>
    </form>
  </div>
</div>
@endsection
