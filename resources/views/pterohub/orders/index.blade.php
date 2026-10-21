@extends('pterohub.layout')
@section('title','Orders')
@section('content')
<div class="orders-ui"><div class="page-heading"><div><div class="eyebrow">COMMERCE / ORDERS</div><h1>Orders</h1><p class="intro">Draft and confirmed orders for internal review. No payments or provisioning.</p></div><a class="button primary" href="{{ route('pterohub.orders.create') }}"> Draft order</a></div>
@if(session('ok'))<div class="notice success">{{ session('ok') }}</div>@endif
<div class="panel"><div class="panel-header"><h2>Order directory</h2><p>Only administrators can access these records.</p></div>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Reference</th><th>Customer</th><th>Status</th><th>Total</th><th>Created</th><th></th></tr></thead><tbody>
@forelse($orders as $order)<tr><td>{{ $order->reference }}</td><td>{{ $order->customer_name }}<br><small>{{ $order->customer_email }}</small></td><td><span class="status-pill">{{ $order->status }}</span></td><td>€ {{ number_format($order->total_minor / 100,2,',','.') }}</td><td>{{ $order->created_at }}</td><td><a class="button subtle" href="{{ route('pterohub.orders.show',$order->id) }}">View </a></td></tr>
@empty<tr><td colspan="6">No orders yet.</td></tr>@endforelse
</tbody></table></div><div style="margin-top:16px">{{ $orders->links() }}</div></div>
</div>
@endsection
