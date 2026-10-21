<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;
final class PterohubNotifications {
 public const EVENTS = [
  'order_confirmed'=>'Order confirmed (internal)',
  'service_provisioned'=>'Server provisioned manually',
  'invoice_simulated'=>'Simulated document issued',
  'payment_simulated'=>'Simulated payment recorded',
 ];
 public static function enabled(string $type): bool {
  if (!isset(self::EVENTS[$type]) || !Schema::hasTable('pterohub_notification_settings')) return false;
  return (bool) DB::table('pterohub_notification_settings')->where('event_type',$type)->value('enabled');
 }
 public static function content(string $type, string $name, string $reference): array {
  $name=trim($name) ?: 'Customer';
  return match($type) {
   'order_confirmed'=>['PteroHub: order confirmed',"Hello {$name},\n\nYour order {$reference} has been confirmed for internal review. No payment was collected and no server was automatically created.\n\nSign in to your PteroHub client portal for details."],
   'service_provisioned'=>['PteroHub: hosting service ready',"Hello {$name},\n\nYour hosting service {$reference} has been provisioned manually. You can view its details and open the Pterodactyl panel from your PteroHub client portal.\n\nProvisioned does not mean the game server is currently online."],
   'invoice_simulated'=>['PteroHub: simulated billing document',"Hello {$name},\n\nA simulated billing document {$reference} has been issued for testing. THIS IS NOT A TAX INVOICE, a payment request, or evidence of payment. No money was charged.\n\nSee your client portal for details."],
   'payment_simulated'=>['PteroHub: simulated payment recorded',"Hello {$name},\n\nA payment has been marked as SIMULATED for document {$reference}. No money was collected or transferred. This is a test record only.\n\nYou can review it in your PteroHub client portal."],
   default=>throw new \InvalidArgumentException('Unknown notification event'),
  };
 }
 // Best effort, never changes commercial/provisioning outcome. Unique event+recipient prevents duplicates.
 public function event(string $type, int $userId, string $reference, string $eventKey): void {
  try {
   if (!self::enabled($type) || !preg_match('/^[a-z0-9:_-]{1,160}$/i',$eventKey)) return;
   $user=DB::table('users')->where('id',$userId)->first(['id','name','email','is_pterohub_admin']);
   if (!$user || $user->is_pterohub_admin || !filter_var($user->email,FILTER_VALIDATE_EMAIL)) return;
   [$subject,$body]=self::content($type,$user->name,$reference);
   $this->recordAndSend($type,$eventKey,(int)$user->id,$user->email,$subject,$body);
  } catch(Throwable $e) {Log::warning('PteroHub notification processing failed',['type'=>$type,'class'=>get_class($e)]);}
 }
 public function testRecipient(int $adminId,string $recipient): void {
  // Dedicated test history record; no customer, order or invoice is touched.
  $subject='PteroHub: notification template test';
  $body="This is a test of the PteroHub HTML notification template.\n\nNo order, payment, invoice or server operation was performed.";
  $key='admin_test:'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(24));
  $now=now();
  $id=DB::table('pterohub_notification_deliveries')->insertGetId([
   'event_key'=>$key,'event_type'=>'admin_test','user_id'=>$adminId,'recipient'=>$recipient,
   'subject'=>$subject,'status'=>'pending','attempts'=>0,'created_at'=>$now,'updated_at'=>$now,
  ]);
  $this->deliver($id,$recipient,$subject,$body);
 }
 private function recordAndSend(string $type,string $key,int $userId,string $recipient,string $subject,string $body): void {
  $now=now();
  $inserted=DB::table('pterohub_notification_deliveries')->insertOrIgnore([
   'event_key'=>$key,'event_type'=>$type,'user_id'=>$userId,'recipient'=>$recipient,
   'subject'=>$subject,'status'=>'pending','attempts'=>0,'created_at'=>$now,'updated_at'=>$now,
  ]);
  if (!$inserted) return;
  $id=(int) DB::table('pterohub_notification_deliveries')->where('event_key',$key)->where('recipient',$recipient)->value('id');
  $this->deliver($id,$recipient,$subject,$body);
 }
 private function deliver(int $id,string $recipient,string $subject,string $body): void {
  try {
   if (config('mail.default')!=='smtp') throw new \RuntimeException('SMTP is not active');
   Mail::html(view('pterohub.email.notification',['subject'=>$subject,'body'=>$body])->render(),function($message) use($recipient,$subject){
    $message->to($recipient)->subject($subject);
   });
   DB::table('pterohub_notification_deliveries')->where('id',$id)->update([
    'status'=>'sent','attempts'=>DB::raw('attempts + 1'),'sent_at'=>now(),
    'last_attempt_at'=>now(),'failure_class'=>null,'updated_at'=>now(),
   ]);
  } catch(Throwable $e) {
   DB::table('pterohub_notification_deliveries')->where('id',$id)->update([
    'status'=>'failed','attempts'=>DB::raw('attempts + 1'),'last_attempt_at'=>now(),
    'failure_class'=>substr(class_basename($e),0,100).(preg_match('/(?:^|\D)([45][0-9]{2})(?:\D|$)/',(string)$e->getMessage(),$matches)?' SMTP '.$matches[1]:''),'updated_at'=>now(),
   ]);
   Log::warning('PteroHub notification delivery failed',['delivery_id'=>$id,'exception_class'=>get_class($e)]);
  }
 }
 public function retry(int $id): bool {
  $delivery=DB::table('pterohub_notification_deliveries')->where('id',$id)->first();
  if (!$delivery || $delivery->status!=='failed' || $delivery->attempts>=3 || !isset(self::EVENTS[$delivery->event_type])) return false;
  $user=DB::table('users')->where('id',$delivery->user_id)->first(['id','name','email','is_pterohub_admin']);
  // Never send to a reassigned address or admin account.
  if (!$user || $user->is_pterohub_admin || strcasecmp($user->email,$delivery->recipient)!==0) return false;
  [$subject,$body]=self::content($delivery->event_type,$user->name,$this->reference($delivery->event_type,$delivery->event_key));
  $claimed=DB::table('pterohub_notification_deliveries')->where('id',$id)->where('status','failed')->where('attempts','<',3)->update(['status'=>'sending','updated_at'=>now()]);
  if (!$claimed) return false;
  $this->deliver($id,$delivery->recipient,$subject,$body);
  return true;
 }
 public function reference(string $type,string $key): string {
  $id=(int)substr(strrchr($key,':') ?: ':0',1);
  if ($id<=0) return 'unknown';
  return match($type) {
   'order_confirmed'=>(string)(DB::table('orders')->where('id',$id)->value('reference') ?? ('#'.$id)),
   'service_provisioned'=>'#'.$id,
   'invoice_simulated','payment_simulated'=>(string)(DB::table('invoices')->where('id',$id)->value('number') ?? ('#'.$id)),
   default=>'unknown',
  };
 }
}
