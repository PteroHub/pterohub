<?php
namespace App\Http\Controllers;

use App\Services\PterohubSandboxPayments as Payments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

final class PterohubSandboxPaymentsController extends Controller {
 public function start(Request $request,int $id,Payments $payments) {
  $user=$request->user();abort_unless($user && !$user->is_pterohub_admin && $user->hasVerifiedEmail(),403);
  $data=$request->validate(['provider'=>'required|in:stripe,paypal']);
  try {return redirect()->away($payments->initiate($id,(int)$user->id,$data['provider']));}
  catch(\Throwable $e){return back()->withErrors(['payment'=>$e->getMessage()]);}
 }
 public function returned(Request $request,string $provider,int $attempt) {
  abort_unless(in_array($provider,['stripe','paypal'],true),404);
  $user=$request->user();abort_unless($user && !$user->is_pterohub_admin,403);
  $row=DB::table('pterohub_sandbox_payment_attempts')->where('id',$attempt)->where('user_id',$user->id)->where('provider',$provider)->first();
  abort_unless($row,404);
  return redirect()->route('ph.client.orders.show',$row->order_id)->with('ok',
   'Returned from sandbox checkout. The provider must verify payment before it appears as Verified. No real payment or provisioning was performed.');
 }
 public function paypalReturn(Request $request,int $attempt) {
  $user=$request->user();abort_unless($user && !$user->is_pterohub_admin,403);
  $row=DB::table('pterohub_sandbox_payment_attempts')->where('id',$attempt)->where('user_id',$user->id)->where('provider','paypal')->first();
  abort_unless($row,404);
  if(!hash_equals((string)$row->provider_order_id,(string)$request->query('token','')))abort(400);
  return redirect()->route('ph.client.orders.show',$row->order_id)->with('ok','PayPal sandbox approval received. Confirm capture from this order page to verify the sandbox payment.');
 }
 public function paypalCapture(Request $request,int $attempt,Payments $payments) {
  $user=$request->user();abort_unless($user && !$user->is_pterohub_admin && $user->hasVerifiedEmail(),403);
  $row=DB::table('pterohub_sandbox_payment_attempts')->where('id',$attempt)->where('user_id',$user->id)->where('provider','paypal')->first();
  abort_unless($row,404);
  try {$payments->paypalCapture($attempt,(int)$user->id);}
  catch(\Throwable $e){Log::warning('PteroHub PayPal sandbox capture needs review',['attempt_id'=>$attempt,'reason'=>$e->getMessage()]);return redirect()->route('ph.client.orders.show',$row->order_id)->withErrors(['payment'=>'PayPal sandbox capture requires review. Do not retry if a capture may have occurred.']);}
  return redirect()->route('ph.client.orders.show',$row->order_id)->with('ok','PayPal sandbox capture verified. No real payment or server activation occurred.');
 }
 public function stripeWebhook(Request $request,Payments $payments) {
  $raw=$request->getContent();
  if(strlen($raw)>200000)abort(413);
  if(!$payments->stripeVerify($raw,$request->header('Stripe-Signature')))abort(400);
  $event=json_decode($raw,true);
  if(!is_array($event))abort(400);
  try {$payments->stripeEvent($event);return response()->json(['received'=>true]);}
  catch(\Throwable $e){Log::warning('PteroHub Stripe sandbox webhook requires review',['reason'=>$e->getMessage()]);return response()->json(['error'=>'review_required'],503);}
 }
 public function paypalWebhook(Request $request,Payments $payments) {
  if(strlen($request->getContent())>200000)abort(413);
  $event=$request->json()->all();if(!is_array($event))abort(400);
  $headers=[];foreach(['paypal-auth-algo','paypal-cert-url','paypal-transmission-id','paypal-transmission-sig','paypal-transmission-time'] as $h)$headers[$h]=$request->header($h,'');
  try {$payments->paypalWebhook($headers,$event);return response()->json(['received'=>true]);}
  catch(\Throwable $e){Log::warning('PteroHub PayPal sandbox webhook rejected/review',['reason'=>$e->getMessage()]);return response()->json(['error'=>'verification_or_review_required'],400);}
 }
 public function admin(Request $request,Payments $payments) {
  abort_unless($request->user() && $request->user()->is_pterohub_admin,403);
  $attempts=DB::table('pterohub_sandbox_payment_attempts as a')->join('orders as o','o.id','=','a.order_id')
   ->join('users as u','u.id','=','a.user_id')->select('a.*','o.reference','o.status as order_status','u.email')
   ->orderByDesc('a.id')->paginate(25);
  $webhooks=DB::table('pterohub_sandbox_webhook_events')->orderByDesc('id')->limit(20)->get();
  return view('pterohub.payments.index',compact('attempts','webhooks','payments'));
 }
}
