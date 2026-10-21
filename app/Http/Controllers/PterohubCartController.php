<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Session-only cart. All monetary amounts are fetched again from the database. */
final class PterohubCartController extends Controller
{
    private const KEY = 'ph_cart_v1';
    private const MAX_LINES = 10;
    private const MAX_UNITS = 20;

    private function cart(Request $request): array
    {
        $stored = $request->session()->get(self::KEY, []);
        if (!is_array($stored)) return [];
        $cart = [];
        foreach ($stored as $key => $qty) {
            if (!is_string($key) || !preg_match('/^[1-9][0-9]*:(1|3|6|12)$/D', $key)) continue;
            if (!is_int($qty) || $qty < 1 || $qty > 5) continue;
            $cart[$key] = $qty;
        }
        return array_slice($cart, 0, self::MAX_LINES, true);
    }

    private function key(int $plan, int $months): string { return $plan.':'.$months; }

    private function pricedRows(array $cart): array
    {
        $rows = []; $total = 0; $available = true;
        foreach ($cart as $key => $quantity) {
            [$planId, $months] = array_map('intval', explode(':', $key));
            $plan = DB::table('hosting_plans as p')
                ->join('games as g', 'g.id', '=', 'p.game_id')
                ->join('pterodactyl_eggs as e', 'e.id', '=', 'p.pterodactyl_egg_id')
                ->join('plan_prices as pr', function ($q) use ($months) {
                    $q->on('pr.hosting_plan_id', '=', 'p.id')
                        ->where('pr.currency', 'EUR')->where('pr.billing_months', $months);
                })
                ->where('p.id', $planId)->where('p.active', true)
                ->where('g.active', true)->where('e.enabled_for_sale', true)
                ->select('p.id', 'p.name', 'p.slug', 'g.name as game_name', 'g.slug as game_slug',
                    'pr.amount_minor', 'pr.setup_minor')->first();
            if (!$plan) {
                $available = false;
                $rows[] = ['key'=>$key,'quantity'=>$quantity,'available'=>false,'name'=>'Unavailable configuration'];
                continue;
            }
            $unit = (int) $plan->amount_minor + (int) $plan->setup_minor;
            // Keep integer arithmetic within PHP's signed 64-bit range.
            if ($unit < 0 || $unit > intdiv(PHP_INT_MAX, self::MAX_UNITS)
                || $total > PHP_INT_MAX - $unit * $quantity) {
                $available = false;
                $rows[] = ['key'=>$key,'quantity'=>$quantity,'available'=>false,'name'=>'Price unavailable'];
                continue;
            }
            $lineTotal = $unit * $quantity;
            $total += $lineTotal;
            $rows[] = ['key'=>$key,'quantity'=>$quantity,'available'=>true,'plan'=>$plan,
                'months'=>$months,'unit_minor'=>$unit,'line_minor'=>$lineTotal,'name'=>$plan->name];
        }
        return [$rows, $total, $available];
    }

    public function show(Request $request)
    {
        $cart = $this->cart($request);
        [$rows, $total, $available] = $this->pricedRows($cart);
        return view('pterohub.client.cart', compact('rows','total','available'));
    }

    public function add(Request $request)
    {
        $data = $request->validate(['plan_id'=>'required|integer|min:1',
            'billing_months'=>'required|integer|in:1,3,6,12']);
        $key = $this->key((int)$data['plan_id'], (int)$data['billing_months']);
        $cart = $this->cart($request);
        if (!isset($cart[$key]) && count($cart) >= self::MAX_LINES) {
            throw ValidationException::withMessages(['cart'=>'The cart supports up to 10 different configurations.']);
        }
        if (array_sum($cart) >= self::MAX_UNITS || ($cart[$key] ?? 0) >= 5) {
            throw ValidationException::withMessages(['cart'=>'The cart quantity limit has been reached.']);
        }
        [$rows, , $available] = $this->pricedRows([$key=>1]);
        if (!$available || empty($rows[0]['available'])) {
            throw ValidationException::withMessages(['cart'=>'This configuration is no longer available.']);
        }
        $cart[$key] = ($cart[$key] ?? 0) + 1;
        $request->session()->put(self::KEY, $cart);
        return redirect()->route('ph.cart.show')->with('ok','Configuration added to cart.');
    }

    public function update(Request $request)
    {
        $data = $request->validate(['key'=>'required|string|max:80', 'quantity'=>'required|integer|min:0|max:5']);
        $cart = $this->cart($request);
        if (!array_key_exists($data['key'], $cart)) abort(404);
        $quantity = (int)$data['quantity'];
        if ($quantity === 0) unset($cart[$data['key']]);
        else $cart[$data['key']] = $quantity;
        if (array_sum($cart) > self::MAX_UNITS) {
            throw ValidationException::withMessages(['quantity'=>'The cart supports at most 20 configurations.']);
        }
        $request->session()->put(self::KEY, $cart);
        return redirect()->route('ph.cart.show')->with('ok','Cart updated.');
    }

    public function remove(Request $request)
    {
        $data = $request->validate(['key'=>'required|string|max:80']);
        $cart = $this->cart($request);
        unset($cart[$data['key']]);
        $request->session()->put(self::KEY, $cart);
        return redirect()->route('ph.cart.show')->with('ok','Item removed.');
    }

    public function checkout(Request $request)
    {
        $user = $request->user();
        abort_unless($user && !$user->is_pterohub_admin && $user->hasVerifiedEmail(), 403);
        $cart = $this->cart($request);
        if (!$cart) return redirect()->route('ph.cart.show')->withErrors(['cart'=>'Your cart is empty.']);
        // Revalidate availability and pricing inside the same DB transaction as the draft order.
        $orderId = DB::transaction(function () use ($cart, $user) {
            [$rows, $total, $available] = $this->pricedRows($cart);
            if (!$available || !$rows) {
                throw ValidationException::withMessages(['cart'=>'Some items or prices have changed. Review your cart.']);
            }
            $now = now();
            $id = DB::table('orders')->insertGetId([
                'user_id'=>$user->id, 'status'=>'draft',
                'reference'=>'DRAFT-'.Str::upper((string)Str::uuid()),
                'currency'=>'EUR', 'total_minor'=>$total,
                'created_at'=>$now, 'updated_at'=>$now,
            ]);
            foreach ($rows as $row) {
                for ($i = 0; $i < $row['quantity']; $i++) {
                    DB::table('order_items')->insert([
                        'order_id'=>$id, 'hosting_plan_id'=>$row['plan']->id,
                        'billing_months'=>$row['months'], 'amount_minor'=>$row['unit_minor'],
                        'configuration'=>json_encode(['kind'=>'client_cart_draft',
                            'plan_name'=>$row['plan']->name,
                            'unit_price_minor'=>(int)$row['plan']->amount_minor,
                            'setup_minor'=>(int)$row['plan']->setup_minor]),
                        'created_at'=>$now, 'updated_at'=>$now,
                    ]);
                }
            }
            return $id;
        });
        $request->session()->forget(self::KEY);
        return redirect()->route('ph.client.orders')->with('ok',
            'Draft order #'.$orderId.' created. No payment or server was created.');
    }
}
