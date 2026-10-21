<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PterohubInvoicesController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->check() && (bool) auth()->user()->is_pterohub_admin, 403);
    }

    public function index(Request $request)
    {
        $this->guard();
        $status = (string)$request->query('status','');
        $search = trim((string)$request->query('q',''));
        $query = DB::table('invoices as i')->join('users as u', 'u.id', '=', 'i.user_id')
            ->select('i.*','u.name as customer_name','u.email as customer_email');
        if(in_array($status,['draft','issued','paid','cancelled'],true)) $query->where('i.status',$status);
        if($search !== '') {
            $term=str_replace(['\\','%','_'],['\\\\','\\%','\\_'],mb_substr($search,0,100));
            $query->where(function($q)use($term,$search){$q->where('u.email','like','%'.$term.'%')->orWhere('i.number','like','%'.$term.'%');if(ctype_digit($search))$q->orWhere('i.id',(int)$search);});
        }
        $invoices=$query->orderByDesc('i.id')->paginate(20)->withQueryString();
        return view('pterohub.invoices.index', compact('invoices','status','search'));
    }

    public function create()
    {
        $this->guard();
        $orders = DB::table('orders as o')->join('users as u','u.id','=','o.user_id')
            ->leftJoin('invoices as i','i.order_id','=','o.id')
            ->whereNull('i.id')->where('o.status','confirmed')
            ->select('o.id','o.reference','o.total_minor','o.currency','u.name as customer_name')
            ->orderByDesc('o.id')->limit(100)->get();
        return view('pterohub.invoices.create',compact('orders'));
    }

    public function store(Request $request)
    {
        $this->guard();
        $data = $request->validate(['order_id'=>['required','integer','exists:orders,id']]);
        $invoiceId = DB::transaction(function () use ($data) {
            $order = DB::table('orders')->where('id',$data['order_id'])->lockForUpdate()->first();
            abort_unless($order,404);
            if ($order->status !== 'confirmed' || $order->currency !== 'EUR') {
                throw ValidationException::withMessages(['order_id'=>'Only confirmed EUR orders can produce a draft document.']);
            }
            if (DB::table('invoices')->where('order_id',$order->id)->exists()) {
                throw ValidationException::withMessages(['order_id'=>'This order already has an invoice.']);
            }
            $items = DB::table('order_items as oi')->join('hosting_plans as p','p.id','=','oi.hosting_plan_id')
                ->where('oi.order_id',$order->id)->select('oi.*','p.name as product_name')->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['order_id'=>'The order has no lines.']);
            }
            $subtotal = 0;
            foreach ($items as $item) {
                if (!is_numeric($item->amount_minor) || (int)$item->amount_minor < 0) {
                    throw ValidationException::withMessages(['order_id'=>'Invalid order item amount.']);
                }
                $subtotal += (int) $item->amount_minor;
            }
            if ($subtotal !== (int)$order->total_minor) {
                throw ValidationException::withMessages(['order_id'=>'Order totals do not reconcile.']);
            }
            $user = DB::table('users')->where('id',$order->user_id)->first();
            abort_unless($user,404);
            $profile = DB::table('pterohub_billing_profiles')->where('user_id',$user->id)->first();
            $snapshot = ['name'=>$user->name,'email'=>$user->email];
            if ($profile) {
                foreach (['customer_type','legal_name','company','tax_id','vat_number','address_line1','address_line2','city','region','postal_code','country','pec_email','recipient_code'] as $key) {
                    $snapshot[$key] = $profile->$key;
                }
            }
            $now = now();
            $id = DB::table('invoices')->insertGetId([
                'user_id'=>$order->user_id,'order_id'=>$order->id,'number'=>null,'status'=>'draft',
                'currency'=>'EUR','subtotal_minor'=>$subtotal,'tax_minor'=>0,'total_minor'=>$subtotal,
                'issued_on'=>null,'due_on'=>null,'paid_at'=>null,
                'customer_snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE),
                'created_at'=>$now,'updated_at'=>$now,
            ]);
            foreach ($items as $item) {
                DB::table('invoice_items')->insert([
                    'invoice_id'=>$id,'description'=>$item->product_name.' — '.$item->billing_months.' month(s)',
                    'quantity'=>1,'unit_price_minor'=>(int)$item->amount_minor,
                    'tax_minor'=>0,'total_minor'=>(int)$item->amount_minor,
                    'created_at'=>$now,'updated_at'=>$now,
                ]);
            }
            DB::table('pterohub_order_events')->insert([
                'order_id' => $order->id, 'actor_user_id' => auth()->id(),
                'action' => 'draft_document_created', 'from_status' => 'confirmed', 'to_status' => 'confirmed',
                'metadata' => json_encode(['invoice_id' => $id]),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('pterohub_invoice_events')->insert([
                'invoice_id'=>$id, 'actor_user_id'=>auth()->id(), 'action'=>'draft_created',
                'from_status'=>null, 'to_status'=>'draft', 'metadata'=>null,
                'created_at'=>$now, 'updated_at'=>$now,
            ]);
            return $id;
        });
        return redirect()->route('pterohub.invoices.show',$invoiceId)
            ->with('ok','Draft document created. No tax calculated, invoice issued, payment taken or server activated.');
    }

    public function show(int $id)
    {
        $this->guard();
        $invoice = DB::table('invoices as i')->join('users as u','u.id','=','i.user_id')
            ->where('i.id',$id)->select('i.*','u.name as customer_name','u.email as customer_email')->first();
        abort_unless($invoice,404);
        $items = DB::table('invoice_items')->where('invoice_id',$id)->orderBy('id')->get();
        $snapshot = json_decode($invoice->customer_snapshot ?? '{}', true) ?: [];
        $events = DB::table('pterohub_invoice_events as e')
            ->leftJoin('users as u','u.id','=','e.actor_user_id')
            ->where('e.invoice_id',$id)->select('e.*','u.name as actor_name')
            ->orderBy('e.id')->get();
        return view('pterohub.invoices.show',compact('invoice','items','snapshot','events'));
    }
    private function recordEvent(int $invoiceId, string $action, ?string $from, string $to, array $metadata = []): void
    {
        DB::table('pterohub_invoice_events')->insert([
            'invoice_id'=>$invoiceId,'actor_user_id'=>auth()->id(),
            'action'=>$action,'from_status'=>$from,'to_status'=>$to,
            'metadata'=>$metadata ? json_encode($metadata) : null,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function issue(Request $request, int $id)
    {
        $this->guard();
        $data = $request->validate(['due_days'=>['required','integer','between:1,90']]);
        DB::transaction(function () use ($id,$data) {
            $invoice = DB::table('invoices')->where('id',$id)->lockForUpdate()->first();
            abort_unless($invoice,404);
            if ($invoice->status !== 'draft' || $invoice->number !== null) {
                throw ValidationException::withMessages(['invoice'=>'Only an unnumbered draft can be issued once.']);
            }
            if (!$invoice->order_id || !DB::table('orders')->where('id',$invoice->order_id)->where('status','confirmed')->exists()) {
                throw ValidationException::withMessages(['invoice'=>'A confirmed source order is required.']);
            }
            $items = DB::table('invoice_items')->where('invoice_id',$id)->get();
            if ($items->isEmpty() || $items->sum('total_minor') != $invoice->total_minor || $items->contains(fn ($item) => (int) $item->tax_minor !== 0) || $invoice->subtotal_minor != $invoice->total_minor || $invoice->tax_minor != 0) {
                throw ValidationException::withMessages(['invoice'=>'Invoice lines and totals must reconcile; tax calculation is not enabled.']);
            }
            // Non-fiscal reference, unique even with concurrent administrator requests.
            $year = (int) now()->year;
            DB::table('pterohub_invoice_sequences')->insertOrIgnore([
                'year'=>$year,'last_number'=>0,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $seq = DB::table('pterohub_invoice_sequences')->where('year',$year)->lockForUpdate()->first();
            $next = (int) $seq->last_number + 1;
            DB::table('pterohub_invoice_sequences')->where('year',$year)->update(['last_number'=>$next,'updated_at'=>now()]);
            $number = 'SIM-'.$year.'-'.str_pad((string)$next,6,'0',STR_PAD_LEFT);
            DB::table('invoices')->where('id',$id)->update([
                'number'=>$number,'status'=>'issued','issued_on'=>today()->toDateString(),
                'due_on'=>today()->addDays((int)$data['due_days'])->toDateString(),
                'updated_at'=>now(),
            ]);
            $this->recordEvent($id,'simulated_issue','draft','issued',['number'=>$number,'due_days'=>(int)$data['due_days']]);
        },3);
        $issued=DB::table('invoices')->where('id',$id)->first(['user_id','number']);
        if ($issued) app(\App\Services\PterohubNotifications::class)->event('invoice_simulated',(int)$issued->user_id,(string)$issued->number,'invoice_simulated:'.$id);
        return redirect()->route('pterohub.invoices.show',$id)->with('ok','Simulated document issued for testing only. This is not a fiscal invoice.');
    }

    public function markPaid(Request $request, int $id)
    {
        $this->guard();
        $request->validate(['confirmation'=>['required','in:SIMULATE']]);
        DB::transaction(function () use ($id) {
            $invoice = DB::table('invoices')->where('id',$id)->lockForUpdate()->first();
            abort_unless($invoice,404);
            if ($invoice->status !== 'issued' || !$invoice->number || $invoice->paid_at) {
                throw ValidationException::withMessages(['invoice'=>'Only an issued, unpaid test document can be marked paid once.']);
            }
            DB::table('invoices')->where('id',$id)->update(['status'=>'paid','paid_at'=>now(),'updated_at'=>now()]);
            $this->recordEvent($id,'simulated_payment','issued','paid',['mode'=>'manual_test_no_transaction']);
        },3);
        $paid=DB::table('invoices')->where('id',$id)->first(['user_id','number']);
        if ($paid) app(\App\Services\PterohubNotifications::class)->event('payment_simulated',(int)$paid->user_id,(string)$paid->number,'payment_simulated:'.$id);
        return redirect()->route('pterohub.invoices.show',$id)->with('ok','Payment simulated. No money was transferred and no hosting service was activated.');
    }

    public function cancel(Request $request, int $id)
    {
        $this->guard();
        $request->validate(['confirmation'=>['required','in:CANCEL']]);
        DB::transaction(function () use ($id) {
            $invoice = DB::table('invoices')->where('id',$id)->lockForUpdate()->first();
            abort_unless($invoice,404);
            if (!in_array($invoice->status,['draft','issued'],true)) {
                throw ValidationException::withMessages(['invoice'=>'Only draft or unpaid issued test documents can be cancelled.']);
            }
            DB::table('invoices')->where('id',$id)->update(['status'=>'cancelled','updated_at'=>now()]);
            $this->recordEvent($id,'simulated_cancel',$invoice->status,'cancelled');
        },3);
        return redirect()->route('pterohub.invoices.show',$id)->with('ok','Test document cancelled; its history has been preserved.');
    }
}
