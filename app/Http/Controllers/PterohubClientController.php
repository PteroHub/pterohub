<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

final class PterohubClientController extends Controller
{
    private function client(): User
    {
        abort_unless(Auth::check(), 401);
        abort_if((bool) Auth::user()->is_pterohub_admin, 403);
        return Auth::user();
    }

    private function turnstile(Request $request): void
    {
        $secret = (string) config('pterohub_client.turnstile_secret');
        if ($secret === '') {
            throw ValidationException::withMessages(['turnstile' => 'Registration is not available until anti-bot protection is configured.']);
        }
        $token = (string) $request->input('cf-turnstile-response', '');
        if ($token === '') throw ValidationException::withMessages(['turnstile' => 'Complete the anti-bot verification.']);
        try {
            $result = Http::asForm()->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret, 'response' => $token, 'remoteip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['turnstile' => 'Anti-bot verification is temporarily unavailable.']);
        }
        if (!$result->successful() || $result->json('success') !== true) {
            throw ValidationException::withMessages(['turnstile' => 'Anti-bot verification failed. Please retry.']);
        }
    }

    /** Public storefront: query only active games and sellable, enabled plans. */
    private function sellablePlans()
    {
        return DB::table('hosting_plans as p')
            ->join('games as g', 'g.id', '=', 'p.game_id')
            ->join('pterodactyl_eggs as e', 'e.id', '=', 'p.pterodactyl_egg_id')
            ->leftJoin('plan_prices as pr', function ($q) {
                $q->on('pr.hosting_plan_id', '=', 'p.id')
                    ->where('pr.currency', '=', 'EUR')->where('pr.billing_months', '=', 1);
            })
            ->where('p.active', true)->where('g.active', true)->where('e.enabled_for_sale', true);
    }

    public function catalog(Request $request)
    {
        $data = $request->validate([
            'game' => 'nullable|string|max:150',
            'q' => 'nullable|string|max:100',
            'sort' => 'nullable|in:name,price_low,price_high',
        ]);
        $games = DB::table('games')->where('active', true)->orderBy('sort_order')->orderBy('name')->get();
        $query = $this->sellablePlans()->select('p.id', 'p.name', 'p.slug', 'p.memory_mb',
            'p.disk_mb', 'p.cpu_percent', 'p.allocations', 'p.backups', 'p.databases',
            'g.name as game_name', 'g.slug as game_slug', 'pr.amount_minor as monthly_minor');
        if (!empty($data['game'])) $query->where('g.slug', $data['game']);
        if (!empty($data['q'])) {
            $search = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $data['q']);
            $query->where(function ($q) use ($search) {
                $q->where('p.name', 'like', '%'.$search.'%')
                  ->orWhere('g.name', 'like', '%'.$search.'%');
            });
        }
        $sort = $data['sort'] ?? 'name';
        if ($sort === 'price_low') $query->orderByRaw('pr.amount_minor IS NULL')->orderBy('pr.amount_minor');
        elseif ($sort === 'price_high') $query->orderByRaw('pr.amount_minor IS NULL')->orderByDesc('pr.amount_minor');
        else $query->orderBy('g.name')->orderBy('p.name');
        $plans = $query->paginate(12)->withQueryString();
        return view('pterohub.client.catalog', compact('games', 'plans', 'sort'));
    }

    public function plan(string $game, string $plan)
    {
        $item = $this->sellablePlans()
            ->where('g.slug', $game)->where('p.slug', $plan)
            ->select('p.*', 'g.name as game_name', 'g.slug as game_slug',
                'g.engine as game_engine', 'g.description as game_description',
                'pr.amount_minor as monthly_minor')->first();
        abort_unless($item, 404);
        $prices = DB::table('plan_prices')->where('hosting_plan_id', $item->id)
            ->where('currency', 'EUR')->whereIn('billing_months', [1, 3, 6, 12])
            ->orderBy('billing_months')->get();
        return view('pterohub.client.plan', compact('item', 'prices'));
    }

    public function registerForm() { return view('pterohub.client.register'); }
    public function register(Request $request)
    {
        abort_unless((bool) config('pterohub_client.registration_enabled'), 503, 'Registration is currently disabled.');
        $data = $request->validate([
            'first_name'=>'required|string|max:150',
            'last_name'=>'required|string|max:150',
            'username'=>'required|string|min:3|max:32|regex:/^[A-Za-z0-9._-]+$/|unique:users,username',
            'email'=>'required|email:rfc|max:254|unique:users,email',
            'password'=>['required','confirmed',PasswordRule::min(12)->letters()->mixedCase()->numbers()],
        ]);
        $this->turnstile($request);
        $user = User::create(['name'=>trim($data['first_name']).' '.trim($data['last_name']),'first_name'=>trim($data['first_name']),'last_name'=>trim($data['last_name']),'username'=>trim($data['username']),'email'=>Str::lower($data['email']),'password'=>$data['password']]);
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('verification.notice');
    }

    public function loginForm()
    {
        // Customer already signed in: use their dashboard. An admin may view
        // this form and sign in as a separate customer account if desired.
        if (Auth::check() && !(bool) Auth::user()->is_pterohub_admin) {
            return redirect()->route('ph.client.dashboard');
        }
        return view('pterohub.client.login');
    }
    public function login(Request $request)
    {
        $data=$request->validate(['email'=>'required|email','password'=>'required|string']);
        if ((string) config('pterohub_client.turnstile_site_key') !== '') $this->turnstile($request);
        if (!Auth::attempt(['email'=>$data['email'],'password'=>$data['password']], false)) {
            throw ValidationException::withMessages(['email'=>'Invalid credentials.']);
        }
        $request->session()->regenerate();
        if ((bool) Auth::user()->is_pterohub_admin) {
            Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
            throw ValidationException::withMessages(['email'=>'Use the administrator login for this account.']);
        }
        return redirect()->route('ph.client.dashboard');
    }
    public function logout(Request $request)
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('ph.client.login');
    }
    public function forgotForm() { return view('pterohub.client.forgot'); }
    public function forgot(Request $request)
    {
        $request->validate(['email'=>'required|email']);
        // Keep account existence private; never allow a public customer reset to target admin users.
        $user = User::query()->where('email', $request->input('email'))->first();
        if ($user && !(bool) $user->is_pterohub_admin) {
            Password::sendResetLink(['email' => $user->email]);
        }
        return back()->with('ok','If an account exists, a password reset email will be sent.');
    }
    public function resetForm(Request $request, string $token)
    {
        return view('pterohub.client.reset', ['token'=>$token,'email'=>$request->query('email','')]);
    }
    public function reset(Request $request)
    {
        $data=$request->validate(['token'=>'required|string','email'=>'required|email',
            'password'=>['required','confirmed',PasswordRule::min(12)->letters()->mixedCase()->numbers()]]);
        $candidate = User::query()->where('email', $data['email'])->first();
        if (!$candidate || (bool) $candidate->is_pterohub_admin) {
            throw ValidationException::withMessages(['email' => 'The reset link is invalid or expired.']);
        }
        $status=Password::reset($data,function(User $user,string $password){
            abort_if((bool) $user->is_pterohub_admin, 403);
            $user->forceFill(['password'=>Hash::make($password),'remember_token'=>Str::random(60)])->save();
        });
        if ($status !== Password::PASSWORD_RESET) throw ValidationException::withMessages(['email'=>__($status)]);
        return redirect()->route('ph.client.login')->with('ok','Password updated. Please sign in.');
    }
    public function dashboard()
    {
        $user=$this->client();
        $counts=['orders'=>DB::table('orders')->where('user_id',$user->id)->count(),
            'services'=>DB::table('hosting_services')->where('user_id',$user->id)->count(),
            'invoices'=>DB::table('invoices')->where('user_id',$user->id)->count(),
            'tickets'=>DB::table('tickets')->where('user_id',$user->id)->count()];
        $recentOrders = DB::table('orders')->where('user_id', $user->id)->orderByDesc('id')->limit(4)->get();
        $recentTickets = DB::table('tickets')->where('user_id', $user->id)->orderByDesc('updated_at')->limit(4)->get();
        $recentServices = DB::table('hosting_services as s')
            ->join('hosting_plans as p', 'p.id', '=', 's.hosting_plan_id')
            ->where('s.user_id', $user->id)
            ->select('s.id', 's.status', 's.next_due_at', 'p.name as plan_name')
            ->orderByDesc('s.id')->limit(4)->get();
        $openTickets = DB::table('tickets')->where('user_id', $user->id)->whereNotIn('status', ['closed'])->count();
        $recentInvoices = DB::table('invoices')->where('user_id', $user->id)
            ->orderByDesc('id')->limit(4)->get();
        $pendingInvoices = DB::table('invoices')->where('user_id', $user->id)
            ->whereNotIn('status', ['paid', 'void', 'cancelled', 'canceled'])->count();
        $dueSoonServices = DB::table('hosting_services')->where('user_id', $user->id)
            ->whereNotNull('next_due_at')->where('next_due_at', '>=', now())
            ->where('next_due_at', '<=', now()->addDays(30))
            ->whereNotIn('status', ['terminated', 'cancelled', 'canceled'])->count();
        return view('pterohub.client.dashboard', compact('counts', 'recentOrders', 'recentTickets', 'recentServices', 'recentInvoices', 'openTickets', 'pendingInvoices', 'dueSoonServices'));
    }
    public function gettingStarted()
    {
        $user = $this->client();
        $profile = DB::table('pterohub_billing_profiles')->where('user_id', $user->id)->first();
        $hasProfile = $profile && (trim((string) ($profile->legal_name ?? '')) !== '' || trim((string) ($profile->company ?? '')) !== '');
        $hasOrders = DB::table('orders')->where('user_id', $user->id)->exists();
        $hasServices = DB::table('hosting_services')->where('user_id', $user->id)->exists();
        $hasTickets = DB::table('tickets')->where('user_id', $user->id)->exists();
        return view('pterohub.client.getting-started', compact('hasProfile', 'hasOrders', 'hasServices', 'hasTickets'));
    }

    public function orders()
    {
        $user=$this->client();
        $rows=DB::table('orders')->where('user_id',$user->id)->orderByDesc('id')->paginate(15);
        return view('pterohub.client.records',['rows'=>$rows,'kind'=>'orders']);
    }
    public function orderShow(int $id)
    {
        $user = $this->client();
        $order = DB::table('orders')->where('id', $id)->where('user_id', $user->id)->first();
        abort_unless($order, 404);
        $items = DB::table('order_items as oi')
            ->leftJoin('hosting_plans as p', 'p.id', '=', 'oi.hosting_plan_id')
            ->where('oi.order_id', $order->id)
            ->select('oi.id', 'oi.hosting_plan_id', 'oi.billing_months', 'oi.amount_minor', 'oi.configuration',
                'p.name as plan_name')->orderBy('oi.id')->get();
        return view('pterohub.client.order-show', compact('order', 'items'));
    }
    public function cancelDraftOrder(Request $request, int $id)
    {
        $user = $this->client();
        DB::transaction(function () use ($user, $id) {
            $order = DB::table('orders')->where('id', $id)->where('user_id', $user->id)
                ->lockForUpdate()->first();
            abort_unless($order, 404);
            abort_unless($order->status === 'draft', 409, 'Only draft orders can be cancelled.');
            $hasInvoice = DB::table('invoices')->where('order_id', $id)->exists();
            $hasService = DB::table('hosting_services as s')
                ->join('order_items as oi', 'oi.id', '=', 's.order_item_id')
                ->where('oi.order_id', $id)->exists();
            abort_if($hasInvoice || $hasService, 409, 'This order has billing or service records and cannot be cancelled here.');
            abort_if(DB::table('pterohub_sandbox_payment_attempts')->where('order_id',$id)->whereIn('status',['initiating','pending','review','verified'])->exists(), 409, 'A sandbox payment attempt requires review before cancellation.');
            DB::table('orders')->where('id', $id)->where('status', 'draft')
                ->update(['status' => 'cancelled', 'updated_at' => now()]);
        });
        return redirect()->route('ph.client.orders.show', $id)
            ->with('ok', 'Draft order cancelled. No payment was taken and no server was created.');
    }

    public function services()
    {
        $user=$this->client();
        $rows=DB::table('hosting_services as s')->join('hosting_plans as p','p.id','=','s.hosting_plan_id')
            ->where('s.user_id',$user->id)->select('s.*','p.name as plan_name')->orderByDesc('s.id')->paginate(15);
        return view('pterohub.client.records',['rows'=>$rows,'kind'=>'services']);
    }
    public function serviceShow(int $id)
    {
        $user = $this->client();
        $service = DB::table('hosting_services as s')
            ->join('hosting_plans as p', 'p.id', '=', 's.hosting_plan_id')
            ->join('games as g', 'g.id', '=', 'p.game_id')
            ->leftJoin('pterodactyl_nodes as n', 'n.id', '=', 's.pterodactyl_node_id')
            ->where('s.id', $id)->where('s.user_id', $user->id)
            ->select('s.id', 's.status', 's.next_due_at', 's.provisioned_at', 's.created_at',
                's.order_item_id', 's.remote_server_id', 's.remote_server_uuid', 's.pterodactyl_node_id',
                'p.name as plan_name', 'p.memory_mb', 'p.disk_mb', 'p.cpu_percent',
                'p.allocations', 'p.backups', 'p.databases', 'g.name as game_name',
                'n.name as node_name')->first();
        abort_unless($service, 404);
        $orderItem = DB::table('order_items')->where('id', $service->order_item_id)->first();
        $order = $orderItem ? DB::table('orders')->where('id', $orderItem->order_id)
            ->where('user_id', $user->id)->first() : null;
        $remote = app(\App\Services\PterohubClientServerSnapshot::class)->inspect($service, (int)$user->id);
        $renewalPlan = DB::table('pterohub_renewal_plans')->where('hosting_service_id',$service->id)->where('status','planned')->first();
        $latestServiceCheck = DB::table('pterohub_service_events')
            ->where('hosting_service_id',$service->id)->where('event_type','remote_sync')
            ->orderByDesc('id')->first();
        return view('pterohub.client.service-show', compact('service', 'order', 'remote', 'latestServiceCheck', 'renewalPlan'));
    }

    public function invoices()
    {
        $user=$this->client();
        $rows=DB::table('invoices')->where('user_id',$user->id)->orderByDesc('id')->paginate(15);
        return view('pterohub.client.records',['rows'=>$rows,'kind'=>'invoices']);
    }
    public function invoiceShow(int $id)
    {
        $user = $this->client();
        $invoice = DB::table('invoices')->where('id', $id)
            ->where('user_id', $user->id)->first();
        abort_unless($invoice, 404);
        $items = DB::table('invoice_items')->where('invoice_id', $invoice->id)
            ->orderBy('id')->get();
        return view('pterohub.client.invoice-show', compact('invoice', 'items'));
    }

    public function tickets()
    {
        $user=$this->client();
        $rows=DB::table('tickets')->where('user_id',$user->id)->orderByDesc('id')->paginate(15);
        return view('pterohub.client.records',['rows'=>$rows,'kind'=>'tickets']);
    }
    public function ticketCreate() { $this->client(); return view('pterohub.client.ticket-create'); }
    public function ticketStore(Request $request)
    {
        $user=$this->client();
        $data=$request->validate(['subject'=>'required|string|max:180','body'=>'required|string|min:10|max:15000']);
        DB::transaction(function()use($user,$data){
            $id=DB::table('tickets')->insertGetId(['user_id'=>$user->id,'subject'=>$data['subject'],
                'department'=>'support','priority'=>'normal','status'=>'open','created_at'=>now(),'updated_at'=>now()]);
            DB::table('ticket_messages')->insert(['ticket_id'=>$id,'user_id'=>$user->id,'body'=>$data['body'],
                'is_internal'=>false,'created_at'=>now(),'updated_at'=>now()]);
        });
        return redirect()->route('ph.client.tickets')->with('ok','Support ticket created.');
    }
    public function ticketShow(int $id)
    {
        $user=$this->client();
        $ticket=DB::table('tickets')->where('id',$id)->where('user_id',$user->id)->first();abort_unless($ticket,404);
        $messages=DB::table('ticket_messages')->where('ticket_id',$id)->where('is_internal',false)->orderBy('id')->get();
        return view('pterohub.client.ticket-show',compact('ticket','messages'));
    }
    public function ticketReply(Request $request,int $id)
    {
        $user=$this->client();
        $data=$request->validate(['body'=>'required|string|min:2|max:15000']);
        DB::transaction(function()use($id,$user,$data){
            $ticket=DB::table('tickets')->where('id',$id)->where('user_id',$user->id)->lockForUpdate()->first();
            abort_unless($ticket,404); abort_if(in_array($ticket->status,['closed'],true),422);
            DB::table('ticket_messages')->insert(['ticket_id'=>$id,'user_id'=>$user->id,'body'=>$data['body'],
                'is_internal'=>false,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('tickets')->where('id',$id)->update(['updated_at'=>now()]);
        });
        return back()->with('ok','Reply sent.');
    }
    public function checkout(Request $request,int $plan)
    {
        $user=$this->client();
        $data=$request->validate(['billing_months'=>'required|integer|in:1,3,6,12']);
        $id=DB::transaction(function()use($plan,$data,$user){
            $p=DB::table('hosting_plans as p')->join('games as g','g.id','=','p.game_id')
                ->join('pterodactyl_eggs as e','e.id','=','p.pterodactyl_egg_id')
                ->where('p.id',$plan)->where('p.active',true)->where('g.active',true)
                ->where('e.enabled_for_sale',true)->select('p.id','p.name')->first();
            abort_unless($p,404);
            $price=DB::table('plan_prices')->where('hosting_plan_id',$p->id)->where('currency','EUR')
                ->where('billing_months',$data['billing_months'])->first();abort_unless($price,422,'Price not configured.');
            $total=(int)$price->amount_minor+(int)$price->setup_minor;
            abort_if($total<0 || $total<max((int)$price->amount_minor,(int)$price->setup_minor),422);
            $now=now();
            $order=DB::table('orders')->insertGetId(['user_id'=>$user->id,'status'=>'draft',
                'reference'=>'DRAFT-'.Str::upper((string)Str::uuid()),'currency'=>'EUR','total_minor'=>$total,
                'created_at'=>$now,'updated_at'=>$now]);
            DB::table('order_items')->insert(['order_id'=>$order,'hosting_plan_id'=>$p->id,
                'billing_months'=>$data['billing_months'],'amount_minor'=>$total,
                'configuration'=>json_encode(['kind'=>'client_draft','plan_name'=>$p->name,
                    'unit_price_minor'=>(int)$price->amount_minor,'setup_minor'=>(int)$price->setup_minor]),
                'created_at'=>$now,'updated_at'=>$now]);
            return $order;
        });
        return redirect()->route('ph.client.orders')->with('ok','Draft order #'.$id.' saved. No payment or server was created.');
    }
    public function security()
    {
        $this->client();
        return view('pterohub.client.security');
    }

    public function passwordUpdate(Request $request)
    {
        $user = $this->client();
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()],
        ]);
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        return redirect()->route('ph.client.security')->with('ok', 'Password updated successfully. Sign out and sign in again to verify your new password.');
    }

    public function profile()
    {
        $user=$this->client();
        $profile=DB::table('pterohub_billing_profiles')->where('user_id',$user->id)->first();
        return view('pterohub.client.profile',compact('profile'));
    }
    public function profileSave(Request $request)
    {
        $user=$this->client();
        $data=$request->validate([
            'customer_type'=>'required|in:individual,business',
            'legal_name'=>'required|string|max:180','company'=>'nullable|string|max:180',
            'tax_id'=>'nullable|string|max:40','vat_number'=>'nullable|string|max:40',
            'address_line1'=>'nullable|string|max:200','address_line2'=>'nullable|string|max:200',
            'city'=>'nullable|string|max:120','region'=>'nullable|string|max:120',
            'postal_code'=>'nullable|string|max:32',
            'country'=>'nullable|regex:/^[A-Za-z]{2}$/',
            'pec_email'=>'nullable|email|max:200','recipient_code'=>'nullable|string|max:20',
        ]);
        if (isset($data['country'])) $data['country']=strtoupper($data['country']);
        if ($data['customer_type'] === 'individual') {
            foreach (['company','vat_number','pec_email','recipient_code'] as $businessField) {
                $data[$businessField] = null;
            }
        }
        $data['updated_at'] = now();
        // updateOrInsert must not replace the original created_at on subsequent saves.
        DB::transaction(function () use ($user, $data) {
            $exists = DB::table('pterohub_billing_profiles')->where('user_id', $user->id)->exists();
            if ($exists) {
                DB::table('pterohub_billing_profiles')->where('user_id', $user->id)->update($data);
            } else {
                DB::table('pterohub_billing_profiles')->insert(array_merge($data, ['user_id' => $user->id, 'created_at' => now()]));
            }
        });
        return back()->with('ok','Billing profile saved.');
    }

}
