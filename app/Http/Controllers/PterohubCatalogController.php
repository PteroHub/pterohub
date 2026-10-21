<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PterohubCatalogController extends Controller
{
    private function guard(): void { abort_unless(Auth::check() && (bool) Auth::user()->is_pterohub_admin, 403); }

    public function updateGame(Request $request, int $id)
    {
        $this->guard();
        $data = $request->validate(['name'=>'required|string|max:150','engine'=>'nullable|string|max:120','description'=>'nullable|string|max:1000']);
        $game = DB::table('games')->where('id',$id)->first(); abort_unless($game,404);
        $slug = Str::slug($data['name']);
        if (!$slug) throw ValidationException::withMessages(['name'=>'Invalid game name.']);
        if (DB::table('games')->where('slug',$slug)->where('id','!=',$id)->exists())
            throw ValidationException::withMessages(['name'=>'Another game has this name.']);
        DB::table('games')->where('id',$id)->update(['name'=>$data['name'],'slug'=>$slug,'engine'=>$data['engine']??null,'description'=>$data['description']??null,'updated_at'=>now()]);
        return back()->with('ok','Game updated. Publication status unchanged.');
    }

    public function updatePlan(Request $request, int $id)
    {
        $this->guard();
        $data=$request->validate([
            'name'=>'required|string|max:150', 'game_id'=>'required|integer|exists:games,id',
            'pterodactyl_egg_id'=>'required|integer|exists:pterodactyl_eggs,id',
            'memory_mb'=>'required|integer|min:128|max:1048576', 'disk_mb'=>'required|integer|min:512|max:10485760',
            'cpu_percent'=>'required|integer|min:1|max:1600', 'allocations'=>'required|integer|min:1|max:20',
            'backups'=>'required|integer|min:0|max:100', 'databases'=>'required|integer|min:0|max:100',
        ]);
        if (!DB::table('pterodactyl_eggs')->where('id',$data['pterodactyl_egg_id'])->where('game_id',$data['game_id'])->exists())
            throw ValidationException::withMessages(['pterodactyl_egg_id'=>'Associate this Egg with the game first.']);
        if (DB::table('order_items')->where('hosting_plan_id',$id)->exists() || DB::table('hosting_services')->where('hosting_plan_id',$id)->exists())
            throw ValidationException::withMessages(['name'=>'This plan has order/service history; create a new plan version rather than changing its resources.']);
        $updated=DB::table('hosting_plans')->where('id',$id)->update($data+['updated_at'=>now()]);
        abort_unless($updated || DB::table('hosting_plans')->where('id',$id)->exists(),404);
        return back()->with('ok','Draft plan updated; no provisioning triggered.');
    }

    public function savePrices(Request $request, int $id)
    {
        $this->guard();
        abort_unless(DB::table('hosting_plans')->where('id',$id)->exists(),404);
        $data=$request->validate([
            'prices'=>'required|array', 'prices.*.amount_minor'=>'nullable|integer|min:0|max:100000000',
            'prices.*.setup_minor'=>'nullable|integer|min:0|max:100000000',
        ]);
        DB::transaction(function () use ($id,$data) {
            foreach ([1,3,6,12] as $months) {
                $p=$data['prices'][$months]??null;
                if (!is_array($p) || !array_key_exists('amount_minor',$p) || $p['amount_minor']===null || $p['amount_minor']==='') continue;
                $where=['hosting_plan_id'=>$id,'currency'=>'EUR','billing_months'=>$months];
                $values=['amount_minor'=>(int)$p['amount_minor'],'setup_minor'=>(int)($p['setup_minor']??0),'updated_at'=>now()];
                if (!DB::table('plan_prices')->where($where)->exists()) $values['created_at']=now();
                DB::table('plan_prices')->updateOrInsert($where,$values);
            }
        });
        return back()->with('ok','Plan prices saved (EUR cents). Existing order snapshots are not changed.');
    }

    public function sync(Request $request)
    {
        $this->guard();
        // Catalog synchronization only. Never provisions servers or enables sales.
        try {
            $code=Artisan::call('pterohub:sync-catalog');
            if ($code!==0) return back()->withErrors(['sync'=>'Catalog synchronization failed. Inspect Laravel logs.']);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['sync'=>'Catalog synchronization failed. Inspect Laravel logs.']);
        }
        return back()->with('ok','Pterodactyl catalog synchronized. Sale flags and associations preserved.');
    }
}
