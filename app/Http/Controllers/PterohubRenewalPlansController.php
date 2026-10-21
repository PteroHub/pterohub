<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class PterohubRenewalPlansController extends Controller {
 private function guard(): void { abort_unless(auth()->check() && (bool)auth()->user()->is_pterohub_admin,403); }
 public function store(Request $request,int $id) {
  $this->guard();
  $data=$request->validate(['planned_for'=>['required','date_format:Y-m-d','after_or_equal:today','before_or_equal:'.today()->addYears(2)->toDateString()], 'note'=>['nullable','string','max:500']]);
  DB::transaction(function()use($id,$data){
   $service=DB::table('hosting_services')->where('id',$id)->lockForUpdate()->first();abort_unless($service,404);
   if(in_array($service->status,['terminated','cancelled'],true))throw ValidationException::withMessages(['planned_for'=>'A terminated service cannot receive a renewal plan.']);
   $now=now();$values=['planned_for'=>$data['planned_for'],'note'=>$data['note']??null,'status'=>'planned','actor_user_id'=>auth()->id(),'updated_at'=>$now];
   if(DB::table('pterohub_renewal_plans')->where('hosting_service_id',$id)->exists()) DB::table('pterohub_renewal_plans')->where('hosting_service_id',$id)->update($values);
   else DB::table('pterohub_renewal_plans')->insert($values+['hosting_service_id'=>$id,'created_at'=>$now]);
   DB::table('pterohub_renewal_plan_events')->insert(['hosting_service_id'=>$id,'actor_user_id'=>auth()->id(),'action'=>'plan_saved','planned_for'=>$data['planned_for'],'created_at'=>$now]);
  },3);
  return redirect()->route('pterohub.services.show',$id)->with('ok','Administrative renewal plan saved. No due date, invoice, payment or server was changed.');
 }
 public function cancel(Request $request,int $id) {
  $this->guard();$request->validate(['confirmation'=>['required','in:CANCEL']]);
  DB::transaction(function()use($id){
   $service=DB::table('hosting_services')->where('id',$id)->lockForUpdate()->first();abort_unless($service,404);
   $plan=DB::table('pterohub_renewal_plans')->where('hosting_service_id',$id)->first();
   if(!$plan||$plan->status!=='planned')throw ValidationException::withMessages(['confirmation'=>'No active renewal plan to cancel.']);
   DB::table('pterohub_renewal_plans')->where('hosting_service_id',$id)->update(['status'=>'cancelled','actor_user_id'=>auth()->id(),'updated_at'=>now()]);
   DB::table('pterohub_renewal_plan_events')->insert(['hosting_service_id'=>$id,'actor_user_id'=>auth()->id(),'action'=>'plan_cancelled','planned_for'=>$plan->planned_for,'created_at'=>now()]);
  },3);
  return redirect()->route('pterohub.services.show',$id)->with('ok','Renewal plan cancelled. Service and billing records are unchanged.');
 }
}
