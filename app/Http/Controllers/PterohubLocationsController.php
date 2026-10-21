<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class PterohubLocationsController extends Controller {
 private function admin(): void { abort_unless(Auth::check() && (bool)Auth::user()->is_pterohub_admin,403); }
 public function index() {
  $this->admin();
  $nodes = DB::table('pterodactyl_nodes')->orderBy('name')->get();
  $plans = DB::table('hosting_plans as p')->join('games as g','g.id','=','p.game_id')->select('p.id','p.name','p.memory_mb','p.disk_mb','p.cpu_percent','g.name as game_name')->orderBy('g.name')->orderBy('p.name')->get();
  $assignments = DB::table('hosting_plan_nodes')->get()->groupBy('hosting_plan_id');
  return view('pterohub.locations.index',compact('nodes','plans','assignments'));
 }
 public function updateNodeSale(Request $request, int $id) {
  $this->admin();
  $node = DB::table('pterodactyl_nodes')->where('id', $id)->first();
  abort_unless($node, 404);
  $data = $request->validate([
   'enabled_for_sale' => ['required', 'boolean'],
   'confirm' => ['required', 'in:CONFIRM'],
  ]);
  DB::table('pterodactyl_nodes')->where('id', $id)->update([
   'enabled_for_sale' => (bool) $data['enabled_for_sale'], 'updated_at' => now(),
  ]);
  return redirect()->route('pterohub.locations.index')->with('ok',
   'Local node sale eligibility updated. No Pterodactyl server was created or changed.');
 }
 public function updatePlanNodes(Request $request,int $id) {
  $this->admin();
  $data=$request->validate(['node_ids'=>'sometimes|array','node_ids.*'=>'integer|distinct|exists:pterodactyl_nodes,id']);
  $ids = array_values(array_unique(array_map('intval',$data['node_ids']??[])));
  DB::transaction(function() use($id,$ids) {
   // Lock the plan so concurrent eligibility changes cannot overwrite each other.
   $plan = DB::table('hosting_plans')->where('id',$id)->lockForUpdate()->first();
   abort_unless($plan,404);
   $existing = DB::table('hosting_plan_nodes')->where('hosting_plan_id',$id)
     ->pluck('pterodactyl_node_id')->map(fn($v)=>(int)$v)->all();
   $removed = array_diff($existing,$ids);
   $hasHistory = DB::table('order_items')->where('hosting_plan_id',$id)->exists()
     || DB::table('hosting_services')->where('hosting_plan_id',$id)->exists();
   if ($hasHistory && count($removed)>0) {
    throw ValidationException::withMessages(['node_ids'=>'This product has order/service history. You may add eligible nodes, but cannot remove existing assignments here.']);
   }
   // Insert only missing links. Never rewrite historical orders or services.
   foreach (array_diff($ids,$existing) as $nodeId) {
    DB::table('hosting_plan_nodes')->insert([
     'hosting_plan_id'=>$id,'pterodactyl_node_id'=>$nodeId,
     'created_at'=>now(),'updated_at'=>now(),
    ]);
   }
   if (!$hasHistory && count($removed)>0) {
    DB::table('hosting_plan_nodes')->where('hosting_plan_id',$id)
      ->whereIn('pterodactyl_node_id',array_values($removed))->delete();
   }
  });
  return redirect()->route('pterohub.locations.index')->with('ok','Eligible nodes updated. No server created or product published.');
 }
}
