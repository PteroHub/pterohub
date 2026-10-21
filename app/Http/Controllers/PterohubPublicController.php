<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

final class PterohubPublicController extends Controller
{
    public function home()
    {
        $games = DB::table('games')->where('active', true)->orderBy('sort_order')->orderBy('name')->limit(8)->get();
        $plans = DB::table('hosting_plans as p')
            ->join('games as g', 'g.id', '=', 'p.game_id')
            ->join('pterodactyl_eggs as e', 'e.id', '=', 'p.pterodactyl_egg_id')
            ->leftJoin('plan_prices as pr', function ($query) {
                $query->on('pr.hosting_plan_id', '=', 'p.id')->where('pr.currency', '=', 'EUR')->where('pr.billing_months', '=', 1);
            })
            ->where('p.active', true)->where('g.active', true)->where('e.enabled_for_sale', true)
            ->select('p.id', 'p.name', 'p.slug', 'g.slug as game_slug', 'p.memory_mb', 'p.disk_mb', 'p.cpu_percent', 'g.name as game_name', 'pr.amount_minor as monthly_minor')
            ->orderBy('g.name')->orderBy('p.name')->limit(6)->get();
        return view('pterohub.public.home', compact('games', 'plans'));
    }
}
