<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
final class PterohubBillingOverviewController extends Controller {
 private function guard(): void { abort_unless(auth()->check() && (bool)auth()->user()->is_pterohub_admin,403); }
 public function index(Request $request) {
  $this->guard();
  $today=today()->toDateString(); $soon=today()->addDays(30)->toDateString();
  $invoiceCounts=DB::table('invoices')->selectRaw('status,COUNT(*) as total,COALESCE(SUM(total_minor),0) as amount_minor')->groupBy('status')->get()->keyBy('status');
  $services=DB::table('hosting_services as s')->join('users as u','u.id','=','s.user_id')
   ->join('hosting_plans as p','p.id','=','s.hosting_plan_id')
   ->leftJoin('pterohub_renewal_plans as r','r.hosting_service_id','=','s.id')
   ->where(function($q) use($soon){$q->whereNotNull('s.next_due_at')->whereDate('s.next_due_at','<=',$soon)->orWhere(function($r)use($soon){$r->where('r.status','planned')->whereDate('r.planned_for','<=',$soon);});})
   ->select('s.id','s.status','s.next_due_at','p.name as plan_name','u.email as customer_email','r.planned_for','r.status as plan_status')
   ->orderBy('s.next_due_at')->orderBy('r.planned_for')->limit(100)->get();
  $plans=DB::table('pterohub_renewal_plans as r')->join('hosting_services as s','s.id','=','r.hosting_service_id')
   ->join('users as u','u.id','=','s.user_id')->join('hosting_plans as p','p.id','=','s.hosting_plan_id')
   ->where('r.status','planned')->select('r.*','u.email as customer_email','p.name as plan_name')
   ->orderBy('r.planned_for')->limit(100)->get();
  $summary=['services'=>DB::table('hosting_services')->count(),
   'overdue'=>DB::table('hosting_services')->whereNotNull('next_due_at')->whereDate('next_due_at','<',$today)->count(),
   'due_30'=>DB::table('hosting_services')->whereNotNull('next_due_at')->whereDate('next_due_at','>=',$today)->whereDate('next_due_at','<=',$soon)->count(),
   'planned'=>DB::table('pterohub_renewal_plans')->where('status','planned')->count()];
  return view('pterohub.billing.overview',compact('summary','invoiceCounts','services','plans'));
 }
}
