<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class PterohubInfrastructureController extends Controller {
 private function guard(): void { abort_unless(auth()->check() && (bool)auth()->user()->is_pterohub_admin,403); }
 public function index() {
  $this->guard();
  $nodes=DB::table('pterodactyl_nodes')->orderBy('name')->get();
  $plans=DB::table('hosting_plans')->orderBy('name')->get();
  $assignments=Schema::hasTable('hosting_plan_nodes') ? DB::table('hosting_plan_nodes')->get()->groupBy('hosting_plan_id') : collect();
  $checks=[];
  foreach ($plans as $plan) {
   $linked=$assignments->get($plan->id,collect())->pluck('pterodactyl_node_id')->all();
   $candidates=$nodes->whereIn('id',$linked);
   $statuses=[];
   foreach($candidates as $node) {
    $payload=json_decode($node->remote_payload ?? '{}',true) ?: [];
    $memory=(int)($payload['memory'] ?? $payload['memory_total'] ?? 0);
    $disk=(int)($payload['disk'] ?? $payload['disk_total'] ?? 0);
    $statuses[]=[ 'name'=>$node->name, 'state'=>(!$node->enabled_for_sale ? 'Node disabled' : 'Unknown capacity'),
      'memory'=>$memory,'disk'=>$disk, 'reason'=>!$node->enabled_for_sale ? 'Node is not enabled for sale' : 'Assigned workloads, free ports, node health and CPU reservations not yet verified' ];
   }
   $checks[$plan->id]=['state'=>count($linked) ? 'Not verified' : 'No assigned node','nodes'=>$statuses];
  }
  return view('pterohub.infrastructure.index',compact('nodes','plans','checks'));
 }
}
