<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PterohubOrdersController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->check() && (bool) auth()->user()->is_pterohub_admin, 403);
    }

    public function index(Request $request)
    {
        $this->guard();
        $orders = DB::table('orders as o')->join('users as u','u.id','=','o.user_id')
            ->select('o.*','u.name as customer_name','u.email as customer_email')
            ->orderByDesc('o.id')->paginate(20);
        return view('pterohub.orders.index', compact('orders'));
    }

    public function create()
    {
        $this->guard();
        $customers=DB::table('users')->select('id','name','email')->orderBy('name')->get();
        $plans=DB::table('hosting_plans as p')->join('games as g','g.id','=','p.game_id')
            ->select('p.id','p.name','g.name as game')->orderBy('g.name')->get();
        $prices=DB::table('plan_prices')->where('currency','EUR')->get();
        return view('pterohub.orders.create',compact('customers','plans','prices'));
    }

    public function store(Request $request)
    {
        $this->guard();
        $data=$request->validate([
            'user_id'=>['required','integer',Rule::exists('users','id')],
            'hosting_plan_id'=>['required','integer',Rule::exists('hosting_plans','id')],
            'billing_months'=>['required','integer',Rule::in([1,3,6,12])],
        ]);
        $plan=DB::table('hosting_plans')->where('id',$data['hosting_plan_id'])->first();
        $price=DB::table('plan_prices')->where('hosting_plan_id',$data['hosting_plan_id'])
            ->where('billing_months',$data['billing_months'])->where('currency','EUR')->first();
        if (!$plan || !$price) {
            throw ValidationException::withMessages(['billing_months'=>'No configured EUR price for this plan and cycle.']);
        }
        $amount=(int)$price->amount_minor;
        $setup=(int)$price->setup_minor;
        $total=$amount+$setup;
        if ($total<0 || $total<max($amount,$setup)) {
            throw ValidationException::withMessages(['hosting_plan_id'=>'Invalid amount.']);
        }
        $id=DB::transaction(function () use ($data,$total,$amount,$setup,$plan) {
            $now=now();
            $id=DB::table('orders')->insertGetId([
                'user_id'=>$data['user_id'],'reference'=>'DRAFT-'.Str::upper((string)Str::uuid()),
                'status'=>'draft','currency'=>'EUR','total_minor'=>$total,
                'created_at'=>$now,'updated_at'=>$now,
            ]);
            DB::table('order_items')->insert([
                'order_id'=>$id,'hosting_plan_id'=>$plan->id,'billing_months'=>$data['billing_months'],
                'amount_minor'=>$total,'configuration'=>json_encode([
                    'kind'=>'admin_draft','unit_price_minor'=>$amount,'setup_minor'=>$setup,
                    'plan_name'=>$plan->name,'created_by_admin'=>auth()->id(),
                ]),'created_at'=>$now,'updated_at'=>$now,
            ]);
            return $id;
        });
        return redirect()->route('pterohub.orders.show',$id)->with('ok','Draft order created. No invoice, payment or server was created.');
    }

    /** Internal commercial confirmation only: never charges or provisions. */
    public function confirm(Request $request, int $id)
    {
        $this->guard();
        DB::transaction(function () use ($id) {
            $order = DB::table('orders')->where('id', $id)->lockForUpdate()->first();
            abort_unless($order, 404);
            if ($order->status !== 'draft') {
                throw ValidationException::withMessages(['order' => 'Only a draft order can be confirmed.']);
            }
            if ($order->currency !== 'EUR' || DB::table('invoices')->where('order_id', $id)->exists()) {
                throw ValidationException::withMessages(['order' => 'This order is not eligible for confirmation (currency or existing document).']);
            }
            if (DB::table('hosting_services as s')->join('order_items as oi','oi.id','=','s.order_item_id')->where('oi.order_id',$id)->exists()) {
                throw ValidationException::withMessages(['order' => 'This order already has service records.']);
            }
            $items = DB::table('order_items')->where('order_id', $id)->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['order' => 'The order has no items.']);
            }
            $sum = 0;
            foreach ($items as $item) {
                if (!is_numeric($item->amount_minor) || (int) $item->amount_minor < 0) {
                    throw ValidationException::withMessages(['order' => 'Invalid order line amount.']);
                }
                $sum += (int) $item->amount_minor;
            }
            if ($sum !== (int) $order->total_minor) {
                throw ValidationException::withMessages(['order' => 'The order totals do not reconcile.']);
            }
            $updated = DB::table('orders')->where('id', $id)->where('status', 'draft')->update([
                'status' => 'confirmed', 'updated_at' => now(),
            ]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['order' => 'The order has already changed.']);
            }
            DB::table('pterohub_order_events')->insert([
                'order_id' => $id, 'actor_user_id' => auth()->id(),
                'action' => 'admin_confirmed', 'from_status' => 'draft', 'to_status' => 'confirmed',
                'metadata' => json_encode(['total_minor' => (int) $order->total_minor, 'currency' => $order->currency]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
        app(\App\Services\PterohubNotifications::class)->event('order_confirmed', (int) DB::table('orders')->where('id',$id)->value('user_id'), (string) DB::table('orders')->where('id',$id)->value('reference'), 'order_confirmed:'.$id);
        return redirect()->route('pterohub.orders.show', $id)
            ->with('ok', 'Order confirmed for internal review. No payment, tax invoice or server was created.');
    }

    public function show(int $id)
    {
        $this->guard();
        $order=DB::table('orders as o')->join('users as u','u.id','=','o.user_id')
            ->select('o.*','u.name as customer_name','u.email as customer_email')->where('o.id',$id)->first();
        abort_unless($order,404);
        $items=DB::table('order_items as oi')->join('hosting_plans as p','p.id','=','oi.hosting_plan_id')
            ->select('oi.*','p.name as plan_name')->where('oi.order_id',$id)->get();
        $events = DB::table('pterohub_order_events as e')->leftJoin('users as u','u.id','=','e.actor_user_id')
            ->where('e.order_id', $id)->select('e.*','u.name as actor_name')->orderBy('e.id')->get();
        $invoice = DB::table('invoices')->where('order_id', $id)->orderBy('id')->first();
        return view('pterohub.orders.show',compact('order','items','events','invoice'));
    }
}
