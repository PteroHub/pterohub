<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PterohubProductsController extends Controller
{
    private function admin(): void { abort_unless(Auth::check() && (bool) Auth::user()->is_pterohub_admin, 403); }

    public function index()
    {
        $this->admin();
        return view('pterohub.products.index', [
            'plans' => DB::table('hosting_plans as p')->join('games as g','g.id','=','p.game_id')
                ->join('pterodactyl_eggs as e','e.id','=','p.pterodactyl_egg_id')
                ->select('p.*','g.name as game_name','e.name as egg_name')->orderBy('g.name')->orderBy('p.name')->get(),
            'prices' => DB::table('plan_prices')->where('currency','EUR')->get()->groupBy('hosting_plan_id'),
        ]);
    }

    public function create()
    {
        $this->admin();
        return view('pterohub.products.editor', $this->referenceData() + ['plan'=>null, 'prices'=>collect()]);
    }

    public function edit(int $id)
    {
        $this->admin();
        $plan = DB::table('hosting_plans')->where('id',$id)->first(); abort_unless($plan,404);
        $prices = DB::table('plan_prices')->where('hosting_plan_id',$id)->where('currency','EUR')->get()->keyBy('billing_months');
        $game = DB::table('games')->where('id', $plan->game_id)->first();
        $egg = DB::table('pterodactyl_eggs')->where('id', $plan->pterodactyl_egg_id)->first();
        return view('pterohub.products.editor', $this->referenceData() + ['plan'=>$plan,'prices'=>$prices,'publicationGame'=>$game,'publicationEgg'=>$egg]);
    }

    /** Hosting Plans and Hosting Products share hosting_plans records. */
    public function confirmDelete(int $id)
    {
        $this->admin();
        $plan = DB::table('hosting_plans')->where('id', $id)->first();
        abort_unless($plan, 404);
        $orderCount = DB::table('order_items')->where('hosting_plan_id', $id)->count();
        $serviceCount = DB::table('hosting_services')->where('hosting_plan_id', $id)->count();
        return view('pterohub.products.delete', compact('plan', 'orderCount', 'serviceCount'));
    }

    /** Delete a plan with no customer history, even when its sale flag is active. */
    public function destroy(Request $request, int $id)
    {
        $this->admin();
        $request->validate(['confirmation' => ['required', 'string', 'in:DELETE']]);

        try {
            return DB::transaction(function () use ($id) {
                $plan = DB::table('hosting_plans')->where('id', $id)->lockForUpdate()->first();
                abort_unless($plan, 404);

                if (DB::table('order_items')->where('hosting_plan_id', $id)->exists()
                    || DB::table('hosting_services')->where('hosting_plan_id', $id)->exists()) {
                    return back()->withErrors(['confirmation' => 'Deletion blocked: this plan has customer orders or services. Their history must be preserved.']);
                }

                // Explicitly remove dependent configuration records before deleting the plan.
                DB::table('hosting_plan_nodes')->where('hosting_plan_id', $id)->delete();
                DB::table('plan_prices')->where('hosting_plan_id', $id)->delete();
                $deleted = DB::table('hosting_plans')->where('id', $id)->delete();
                if ($deleted !== 1) {
                    throw new \RuntimeException('Expected to delete exactly one hosting plan.');
                }
                return redirect()->route('pterohub.products.index')
                    ->with('ok', 'Hosting plan / product deleted successfully: ' . $plan->name);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);
            return back()->withErrors(['confirmation' => 'Deletion failed because a database relation still references this plan. Check storage/logs/laravel.log for details.']);
        }
    }

    /** Publish a catalog item for draft-order testing; this never provisions or charges. */
    public function publication(Request $request, int $id)
    {
        $this->admin();
        $data = $request->validate([
            'status' => ['required', 'in:active,draft'],
            'enable_game' => ['sometimes', 'accepted'],
            'enable_egg' => ['sometimes', 'accepted'],
        ]);
        return DB::transaction(function () use ($id, $data) {
            $plan = DB::table('hosting_plans')->where('id', $id)->lockForUpdate()->first();
            abort_unless($plan, 404);
            if ($data['status'] === 'draft') {
                DB::table('hosting_plans')->where('id', $id)->update(['active'=>false,'updated_at'=>now()]);
                return redirect()->route('pterohub.products.edit', $id)->with('ok', 'Product unpublished. Existing draft orders are preserved.');
            }
            $game = DB::table('games')->where('id', $plan->game_id)->first();
            $egg = DB::table('pterodactyl_eggs')->where('id', $plan->pterodactyl_egg_id)->first();
            if (!$game || !$egg || (int)$egg->game_id !== (int)$plan->game_id) {
                throw ValidationException::withMessages(['publication' => 'Game and Egg must be correctly associated before publishing.']);
            }
            if (!DB::table('plan_prices')->where('hosting_plan_id', $id)->where('currency', 'EUR')->whereIn('billing_months', [1,3,6,12])->exists()) {
                throw ValidationException::withMessages(['publication' => 'Configure at least one EUR billing price before publishing.']);
            }
            if (!(bool)$game->active && !($data['enable_game'] ?? false)) {
                throw ValidationException::withMessages(['publication' => 'The game is disabled. Explicitly authorize enabling it to publish this product.']);
            }
            if (!(bool)$egg->enabled_for_sale && !($data['enable_egg'] ?? false)) {
                throw ValidationException::withMessages(['publication' => 'The Egg is disabled for sale. Explicitly authorize enabling it to publish this product.']);
            }
            if (!(bool)$game->active) DB::table('games')->where('id', $game->id)->update(['active'=>true,'updated_at'=>now()]);
            if (!(bool)$egg->enabled_for_sale) DB::table('pterodactyl_eggs')->where('id', $egg->id)->update(['enabled_for_sale'=>true,'updated_at'=>now()]);
            DB::table('hosting_plans')->where('id', $id)->update(['active'=>true,'updated_at'=>now()]);
            return redirect()->route('pterohub.products.edit', $id)->with('ok', 'Product published in catalog. Only draft orders are possible; payments and provisioning remain disabled.');
        });
    }

    private function referenceData(): array
    {
        return [
            'games'=>DB::table('games')->orderBy('name')->get(),
            'eggs'=>DB::table('pterodactyl_eggs')->whereNotNull('game_id')->orderBy('name')->get(),
            'nodes'=>DB::table('pterodactyl_nodes')->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request): array
    {
        $data=$request->validate([
            'name'=>'required|string|max:150','game_id'=>'required|integer|exists:games,id',
            'pterodactyl_egg_id'=>'required|integer|exists:pterodactyl_eggs,id',
            'memory_mb'=>'required|integer|min:128|max:1048576','disk_mb'=>'required|integer|min:512|max:10485760',
            'cpu_percent'=>'required|integer|min:1|max:1600','swap_mb'=>'nullable|integer|min:0|max:65535',
            'allocations'=>'required|integer|min:1|max:20','backups'=>'required|integer|min:0|max:100',
            'databases'=>'required|integer|min:0|max:100', 'slot_limit'=>'nullable|integer|min:1|max:512',
        ]);
        if (!DB::table('pterodactyl_eggs')->where('id',$data['pterodactyl_egg_id'])->where('game_id',$data['game_id'])->exists()) {
            throw ValidationException::withMessages(['pterodactyl_egg_id'=>'This Egg must be associated with the selected game.']);
        }
        $data['swap_mb']=$data['swap_mb']??0;
        $data['slot_limit']=$data['slot_limit']??null;
        return $data;
    }

    public function store(Request $request)
    {
        $this->admin(); $data=$this->validated($request);
        $data['slug']=Str::slug($data['name']).'-'.Str::lower(Str::random(8));
        $data['active']=false; $data['created_at']=now(); $data['updated_at']=now();
        $id=DB::table('hosting_plans')->insertGetId($data);
        return redirect()->route('pterohub.products.edit',$id)->with('ok','Draft product created. Configure prices before publishing in a future release.');
    }

    public function update(Request $request,int $id)
    {
        $this->admin(); $plan=DB::table('hosting_plans')->where('id',$id)->first();abort_unless($plan,404);
        $data=$this->validated($request);
        if (DB::table('order_items')->where('hosting_plan_id',$id)->exists() || DB::table('hosting_services')->where('hosting_plan_id',$id)->exists()) {
            throw ValidationException::withMessages(['name'=>'Plan has existing order/service history. Create a new plan instead of changing resources.']);
        }
        // Never activate sale via this editor, even if submitted manually.
        DB::table('hosting_plans')->where('id',$id)->update($data+['updated_at'=>now()]);
        return redirect()->route('pterohub.products.edit',$id)->with('ok','Draft product updated. Sale status unchanged.');
    }

    public function prices(Request $request,int $id)
    {
        $this->admin();abort_unless(DB::table('hosting_plans')->where('id',$id)->exists(),404);
        $data=$request->validate([
            'periods'=>'sometimes|array', 'periods.*'=>'integer|in:1,3,6,12',
            'amount'=>'sometimes|array', 'amount.*'=>'nullable|numeric|min:0|max:1000000|regex:/^\\d+(\\.\\d{1,2})?$/',
            'setup'=>'sometimes|array','setup.*'=>'nullable|numeric|min:0|max:1000000|regex:/^\\d+(\\.\\d{1,2})?$/',
        ]);
        $enabled=array_map('intval',$data['periods']??[]);
        foreach ($enabled as $months) {
            $amount=$data['amount'][$months]??null;
            if ($amount===null || $amount==='') throw ValidationException::withMessages(['amount'=>'Enter a price for every enabled billing period.']);
        }
        DB::transaction(function () use ($id,$data,$enabled) {
            foreach([1,3,6,12] as $months) {
                $key=['hosting_plan_id'=>$id,'currency'=>'EUR','billing_months'=>$months];
                if(!in_array($months,$enabled,true)) {
                    DB::table('plan_prices')->where($key)->delete();continue;
                }
                $amount=(int)round(((float)$data['amount'][$months])*100);
                $setup=(int)round(((float)($data['setup'][$months]??0))*100);
                $values=['amount_minor'=>$amount,'setup_minor'=>$setup,'updated_at'=>now()];
                if (!DB::table('plan_prices')->where($key)->exists()) $values['created_at']=now();
                DB::table('plan_prices')->updateOrInsert($key,$values);
            }
        });
        return redirect()->route('pterohub.products.edit',$id)->with('ok','Billing prices saved. No payment gateway or provisioning was triggered.');
    }
}
