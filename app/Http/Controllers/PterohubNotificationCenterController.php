<?php
namespace App\Http\Controllers;
use App\Services\PterohubNotifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
final class PterohubNotificationCenterController extends Controller {
 private function guard(): void {abort_unless(auth()->check() && (bool)auth()->user()->is_pterohub_admin,403);abort_unless(Schema::hasTable('pterohub_notification_deliveries'),503,'Run migrations first.');}
 public function index(Request $request) {
  $this->guard();
  $settings=DB::table('pterohub_notification_settings')->pluck('enabled','event_type')->all();
  $q=DB::table('pterohub_notification_deliveries as d')->leftJoin('users as u','u.id','=','d.user_id')
   ->select('d.*','u.name as customer_name');
  if(in_array($request->query('status'),['sent','failed','pending','sending'],true)) $q->where('d.status',$request->query('status'));
  if(array_key_exists((string)$request->query('event_type'),PterohubNotifications::EVENTS) || $request->query('event_type')==='admin_test') $q->where('d.event_type',$request->query('event_type'));
  $deliveries=$q->orderByDesc('d.id')->paginate(25)->withQueryString();
  return view('pterohub.email.notifications',compact('settings','deliveries'));
 }
 public function save(Request $request) {
  $this->guard();
  $data=$request->validate(['events'=>['nullable','array'],'events.*'=>['string',Rule::in(array_keys(PterohubNotifications::EVENTS))]]);
  $enabled=array_unique($data['events']??[]);
  DB::transaction(function() use($enabled){
   foreach(PterohubNotifications::EVENTS as $key=>$label) {
    DB::table('pterohub_notification_settings')->insertOrIgnore(['event_type'=>$key,'enabled'=>false,'created_at'=>now(),'updated_at'=>now()]);
    DB::table('pterohub_notification_settings')->where('event_type',$key)->update(['enabled'=>in_array($key,$enabled,true),'updated_at'=>now()]);
   }
  });
  return back()->with('ok','Notification preferences saved. Only future events generate emails.');
 }
 public function preview(Request $request) {
  $this->guard();$type=$request->validate(['type'=>['required',Rule::in(array_keys(PterohubNotifications::EVENTS))]])['type'];
  [$subject,$body]=PterohubNotifications::content($type,'Example Customer','DEMO-123');
  return view('pterohub.email.notification',compact('subject','body'));
 }
 public function test(Request $request,PterohubNotifications $notifications) {
  $this->guard();
  $data=$request->validate(['recipient'=>['required','email:rfc','max:254']]);
  \App\Support\PterohubMailSettings::apply();
  if(config('mail.default')!=='smtp') return back()->withErrors(['recipient'=>'SMTP must be enabled to send a notification test.']);
  $notifications->testRecipient((int)auth()->id(),$data['recipient']);
  return back()->with('ok','Notification test attempted. Check Delivery history for the SMTP result.');
 }
 public function retry(int $id,PterohubNotifications $notifications) {
  $this->guard();
  if(!$notifications->retry($id)) return back()->withErrors(['retry'=>'This delivery cannot be retried (already sent, changed recipient or retry limit reached).']);
  return back()->with('ok','Retry attempted. Check the updated delivery status.');
 }
}
