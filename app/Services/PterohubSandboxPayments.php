<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/** Sandbox payment evidence only. Never modifies orders, invoices, services or real payments. */
final class PterohubSandboxPayments {
 public function enabled(string $provider): bool {
  if(!config('pterohub_payments.enabled')) return false;
  if($provider==='stripe') return str_starts_with((string)config('pterohub_payments.stripe.secret'),'sk_test_') && str_starts_with((string)config('pterohub_payments.stripe.webhook_secret'),'whsec_');
  if($provider==='paypal') return filled(config('pterohub_payments.paypal.client_id')) && filled(config('pterohub_payments.paypal.client_secret')) && filled(config('pterohub_payments.paypal.webhook_id'));
  return false;
 }
 public function order(int $id,int $userId): object {
  $o=DB::table('orders')->where('id',$id)->where('user_id',$userId)->first();
  if(!$o || $o->status!=='draft' || $o->currency!=='EUR' || (int)$o->total_minor<50 || (int)$o->total_minor>100000000) throw new RuntimeException('order_not_eligible');
  if(DB::table('invoices')->where('order_id',$id)->exists() || DB::table('hosting_services as s')->join('order_items as oi','oi.id','=','s.order_item_id')->where('oi.order_id',$id)->exists()) throw new RuntimeException('order_already_processed');
  $items=DB::table('order_items')->where('order_id',$id)->get();
  if($items->isEmpty() || $items->sum('amount_minor')!=(int)$o->total_minor) throw new RuntimeException('order_total_mismatch');
  return $o;
 }
 public function initiate(int $id,int $userId,string $provider): string {
  if(!$this->enabled($provider)) throw new RuntimeException('sandbox_provider_not_configured');
  $o=$this->order($id,$userId);
  $active=DB::table('pterohub_sandbox_payment_attempts')->where('order_id',$id)->whereIn('status',['initiating','pending','verified'])->exists();
  if($active) throw new RuntimeException('existing_attempt_review_required');
  $attempt=DB::table('pterohub_sandbox_payment_attempts')->insertGetId([
   'order_id'=>$id,'user_id'=>$userId,'provider'=>$provider,'status'=>'initiating',
   'amount_minor'=>$o->total_minor,'currency'=>'EUR','created_at'=>now(),'updated_at'=>now(),
  ]);
  try {
   if($provider==='stripe') {
    $res=Http::withToken(config('pterohub_payments.stripe.secret'))->asForm()->timeout(20)->post('https://api.stripe.com/v1/checkout/sessions',[
     'mode'=>'payment','client_reference_id'=>(string)$attempt,
     'success_url'=>route('ph.sandbox.return',['provider'=>'stripe','attempt'=>$attempt,'result'=>'success']),
     'cancel_url'=>route('ph.sandbox.return',['provider'=>'stripe','attempt'=>$attempt,'result'=>'cancel']),
     'line_items'=>[['price_data'=>['currency'=>'eur','unit_amount'=>(int)$o->total_minor,'product_data'=>['name'=>'PteroHub test order #'.$id]],'quantity'=>1]],
     'metadata'=>['pterohub_attempt'=>(string)$attempt,'pterohub_order'=>(string)$id],
     'payment_intent_data'=>['metadata'=>['pterohub_attempt'=>(string)$attempt]],
   ]);
    $data=$res->json();
    if(!$res->successful() || !is_array($data) || !str_starts_with((string)($data['id']??''),'cs_test_') || !str_starts_with((string)($data['url']??''),'https://checkout.stripe.com/')) throw new RuntimeException('stripe_checkout_creation_failed');
    $remoteId=$data['id'];$url=$data['url'];
   } else {
    $token=$this->paypalToken();
    $res=Http::withToken($token)->acceptJson()->withHeaders(['PayPal-Request-Id'=>'ph-sandbox-'.$attempt])->timeout(20)
     ->post('https://api-m.sandbox.paypal.com/v2/checkout/orders',[
      'intent'=>'CAPTURE','purchase_units'=>[['reference_id'=>(string)$attempt,'custom_id'=>(string)$attempt,
       'amount'=>['currency_code'=>'EUR','value'=>number_format((int)$o->total_minor/100,2,'.','')]]],
      'payment_source'=>['paypal'=>['experience_context'=>[
       'return_url'=>route('ph.sandbox.paypal.return',['attempt'=>$attempt]),
       'cancel_url'=>route('ph.sandbox.return',['provider'=>'paypal','attempt'=>$attempt,'result'=>'cancel']),
       'user_action'=>'PAY_NOW',
      ]]],
     ]);
    $data=$res->json();
    $url=collect($data['links']??[])->firstWhere('rel','payer-action')['href']??null;
    if(!$res->successful() || !is_string($data['id']??null) || !is_string($url) || !str_starts_with($url,'https://www.sandbox.paypal.com/')) throw new RuntimeException('paypal_checkout_creation_failed');
    $remoteId=$data['id'];
   }
   DB::table('pterohub_sandbox_payment_attempts')->where('id',$attempt)->update(['provider_order_id'=>$remoteId,'status'=>'pending','updated_at'=>now()]);
   return $url;
  } catch(\Throwable $e) {
   DB::table('pterohub_sandbox_payment_attempts')->where('id',$attempt)->update(['status'=>'review','failure_code'=>'initiation_failed_or_uncertain','updated_at'=>now()]);
   Log::warning('PteroHub sandbox initiation needs review',['attempt_id'=>$attempt,'provider'=>$provider,'exception'=>get_class($e)]);
   throw new RuntimeException('sandbox_initiation_failed_review_required');
  }
 }
 public function paypalToken(): string {
  $res=Http::withBasicAuth((string)config('pterohub_payments.paypal.client_id'),(string)config('pterohub_payments.paypal.client_secret'))
   ->asForm()->timeout(15)->post('https://api-m.sandbox.paypal.com/v1/oauth2/token',['grant_type'=>'client_credentials']);
  if(!$res->successful() || !is_string($res->json('access_token'))) throw new RuntimeException('paypal_sandbox_auth_failed');
  return $res->json('access_token');
 }
 public function stripeVerify(string $raw,?string $signature): bool {
  $secret=(string)config('pterohub_payments.stripe.webhook_secret');
  if(!$this->enabled('stripe') || !is_string($signature)) return false;
  $pairs=[];
  foreach(explode(',',$signature) as $part){$bits=explode('=',trim($part),2);if(count($bits)===2)$pairs[$bits[0]][]=$bits[1];}
  $ts=$pairs['t'][0]??'';
  if(!ctype_digit($ts) || abs(time()-(int)$ts)>300) return false;
  $expected=hash_hmac('sha256',$ts.'.'.$raw,$secret);
  foreach($pairs['v1']??[] as $candidate) if(is_string($candidate)&&hash_equals($expected,$candidate))return true;
  return false;
 }
 public function stripeEvent(array $event): void {
  $eventId=(string)($event['id']??'');$type=(string)($event['type']??'');
  if(!preg_match('/^evt_[A-Za-z0-9]+$/',$eventId)) throw new RuntimeException('invalid_stripe_event');
  $this->eventOnce('stripe',$eventId,$type,function()use($event,$type){
   if($type!=='checkout.session.completed') return 'ignored';
   $session=$event['data']['object']??[];
   $id=(string)($session['id']??'');
   if(!str_starts_with($id,'cs_test_')) return 'ignored';
   // Fetch from Stripe: never trust the webhook body alone for settlement.
   $res=Http::withToken(config('pterohub_payments.stripe.secret'))->timeout(15)->get('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($id));
   if(!$res->successful()) throw new RuntimeException('stripe_session_fetch_failed');
   $s=$res->json();$attemptId=(int)($s['metadata']['pterohub_attempt']??0);
   return $this->settle($attemptId,'stripe',$id,(string)($s['payment_status']??'')==='paid',
    (int)($s['amount_total']??-1),strtoupper((string)($s['currency']??'')),null);
  });
 }
 public function paypalCapture(int $attemptId,int $userId): void {
  if(!$this->enabled('paypal')) throw new RuntimeException('paypal_not_configured');
  $attempt=DB::table('pterohub_sandbox_payment_attempts')->where('id',$attemptId)->where('user_id',$userId)->where('provider','paypal')->first();
  if(!$attempt || !in_array($attempt->status,['pending','verified'],true)) throw new RuntimeException('invalid_payment_attempt');
  if($attempt->status==='verified') return;
  $token=$this->paypalToken();
  $check=Http::withToken($token)->acceptJson()->timeout(15)->get('https://api-m.sandbox.paypal.com/v2/checkout/orders/'.rawurlencode($attempt->provider_order_id));
  $remote=$check->json();
  if(!$check->successful() || ($remote['status']??'')!=='APPROVED' || (string)($remote['purchase_units'][0]['custom_id']??'')!==(string)$attemptId) throw new RuntimeException('paypal_order_not_approved');
  $res=Http::withToken($token)->acceptJson()->withHeaders(['PayPal-Request-Id'=>'ph-sandbox-capture-'.$attemptId])->timeout(20)
   ->post('https://api-m.sandbox.paypal.com/v2/checkout/orders/'.rawurlencode($attempt->provider_order_id).'/capture',[]);
  if(!$res->successful()) {
   DB::table('pterohub_sandbox_payment_attempts')->where('id',$attemptId)->where('status','pending')->update(['status'=>'review','failure_code'=>'paypal_capture_uncertain','updated_at'=>now()]);
   throw new RuntimeException('paypal_capture_uncertain_review_required');
  }
  $data=$res->json();$unit=$data['purchase_units'][0]??[];
  $captures=$unit['payments']['captures']??[];$capture=$captures[0]??[];
  $amount=$capture['amount']??[];
  $minor=$this->minor((string)($amount['value']??''));
  $this->settle($attemptId,'paypal',(string)($data['id']??''),
   ($data['status']??'')==='COMPLETED' && ($capture['status']??'')==='COMPLETED' && (string)($unit['custom_id']??'')===(string)$attemptId,
   $minor,(string)($amount['currency_code']??''),(string)($capture['id']??''));
 }
 private function minor(string $value): int {
  if(!preg_match('/^\d{1,9}\.\d{2}$/D',$value))return -1;
  [$a,$b]=explode('.',$value);return (int)$a*100+(int)$b;
 }
 private function settle(int $id,string $provider,string $remoteId,bool $paid,int $amount,string $currency,?string $captureId): string {
  return DB::transaction(function()use($id,$provider,$remoteId,$paid,$amount,$currency,$captureId){
   $a=DB::table('pterohub_sandbox_payment_attempts')->where('id',$id)->lockForUpdate()->first();
   if(!$a || $a->provider!==$provider || !hash_equals((string)$a->provider_order_id,$remoteId)) return 'unmatched';
   if($a->status==='verified') return 'already_verified';
   if($a->status==='initiating') throw new RuntimeException('payment_attempt_not_ready');
   if(!in_array($a->status,['pending','review'],true)) return 'not_pending';
   if(!$paid || $amount!==(int)$a->amount_minor || $currency!==$a->currency || ($provider==='paypal' && !$captureId)) {
    DB::table('pterohub_sandbox_payment_attempts')->where('id',$id)->update(['status'=>'review','failure_code'=>'provider_amount_or_status_mismatch','updated_at'=>now()]);
    return 'review';
   }
   $order=DB::table('orders')->where('id',$a->order_id)->lockForUpdate()->first();
   if(!$order || $order->user_id!==$a->user_id || $order->status!=='draft' || $order->currency!==$a->currency || (int)$order->total_minor!==$amount) return 'order_changed_review';
   DB::table('pterohub_sandbox_payment_attempts')->where('id',$id)->update(['status'=>'verified','provider_capture_id'=>$captureId,'verified_at'=>now(),'failure_code'=>null,'updated_at'=>now()]);
   return 'verified';
  },3);
 }
 private function eventOnce(string $provider,string $eventId,string $type,callable $fn): void {
  $inserted=DB::table('pterohub_sandbox_webhook_events')->insertOrIgnore(['provider'=>$provider,'event_id'=>$eventId,'event_type'=>substr($type,0,100),'status'=>'processing','created_at'=>now(),'updated_at'=>now()]);
  if(!$inserted){
   $prior=DB::table('pterohub_sandbox_webhook_events')->where('provider',$provider)->where('event_id',$eventId)->first();
   if($prior && $prior->status==='processed')return;
   // A failed attempt is reviewable; never replay automatically without operator action.
   throw new RuntimeException('webhook_processing_review_required');
  }
  try {
   $status=$fn();
   DB::table('pterohub_sandbox_webhook_events')->where('provider',$provider)->where('event_id',$eventId)->update(['status'=>'processed','failure_code'=>substr((string)$status,0,120),'updated_at'=>now()]);
  } catch(\Throwable $e){
   DB::table('pterohub_sandbox_webhook_events')->where('provider',$provider)->where('event_id',$eventId)->update(['status'=>'review','failure_code'=>'processing_failed','updated_at'=>now()]);
   throw $e;
  }
 }
 public function paypalWebhook(array $headers,array $event): void {
  if(!$this->enabled('paypal'))throw new RuntimeException('paypal_not_configured');
  $token=$this->paypalToken();
  $verification=Http::withToken($token)->acceptJson()->timeout(15)->post('https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature',[
   'auth_algo'=>$headers['paypal-auth-algo']??'',
   'cert_url'=>$headers['paypal-cert-url']??'',
   'transmission_id'=>$headers['paypal-transmission-id']??'',
   'transmission_sig'=>$headers['paypal-transmission-sig']??'',
   'transmission_time'=>$headers['paypal-transmission-time']??'',
   'webhook_id'=>config('pterohub_payments.paypal.webhook_id'),
   'webhook_event'=>$event,
  ]);
  if(!$verification->successful() || $verification->json('verification_status')!=='SUCCESS') throw new RuntimeException('paypal_webhook_signature_invalid');
  $eventId=(string)($event['id']??'');$type=(string)($event['event_type']??'');
  if(!preg_match('/^[A-Za-z0-9-]{8,255}$/D',$eventId))throw new RuntimeException('invalid_paypal_event');
  $this->eventOnce('paypal',$eventId,$type,function()use($type){
   // Captures are reconciled only by the authenticated return endpoint in RC 1.00.
   // Verified webhook events are logged without changing order or settlement state.
   return $type==='PAYMENT.CAPTURE.COMPLETED'?'verified_event_requires_capture_reconciliation':'ignored';
  });
 }
}
