<?php
namespace App\Http\Controllers;
use App\Support\PterohubMailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;
final class PterohubEmailController extends Controller {
 private function guard(): void {abort_unless(auth()->check() && (bool)auth()->user()->is_pterohub_admin,403);}
 public function index() {
  $this->guard(); $record=PterohubMailSettings::record();
  return view('pterohub.email.index',[
   'stored'=>$record,'mailer'=>config('mail.default'),'host'=>config('mail.mailers.smtp.host'),
   'port'=>config('mail.mailers.smtp.port'),'from'=>config('mail.from.address'),
   'name'=>config('mail.from.name'),'queue'=>config('queue.default'),
   'ticketEmails'=>config('pterohub_notifications.ticket_emails',false),
  ]);
 }
 public function update(Request $request) {
  $this->guard(); abort_unless(Schema::hasTable('pterohub_mail_settings'),503,'Run migrations first.');
  $v=$request->validate([
   'driver'=>['required',Rule::in(['smtp','log'])],
   'host'=>['nullable','required_if:driver,smtp','string','max:255'],
   'port'=>['nullable','required_if:driver,smtp','integer','between:1,65535'],
   'scheme'=>['nullable',Rule::in(['smtp','smtps'])],
   'username'=>['nullable','string','max:254'],
   'password'=>['nullable','string','max:4096'],
   'from_address'=>['nullable','required_if:driver,smtp','email','max:254'],
   'reply_to'=>['nullable','email','max:254'],
  ]);
  $existing=PterohubMailSettings::record();
  $password=$existing?->encrypted_password;
  if (isset($v['password']) && $v['password']!=='') $password=Crypt::encryptString($v['password']);
  $values=[
   'driver'=>$v['driver'],'host'=>$v['host']??null,'port'=>$v['port']??null,
   'scheme'=>$v['scheme']??null,'username'=>$v['username']??null,
   'encrypted_password'=>$password,'from_address'=>$v['from_address']??null,
   'reply_to'=>$v['reply_to']??null,'updated_at'=>now(),
  ];
  DB::transaction(function() use($values,$existing) {
   if ($existing) DB::table('pterohub_mail_settings')->where('id',1)->update($values);
   else DB::table('pterohub_mail_settings')->insert(['id'=>1,'created_at'=>now()]+$values);
  });
  PterohubMailSettings::apply();
  return redirect()->route('pterohub.email.index')->with('ok','Email settings saved. Send a test before enabling notifications.');
 }
 public function test(Request $request) {
  $this->guard(); $data=$request->validate(['recipient'=>['required','email','max:254']]);
  PterohubMailSettings::apply();
  if (config('mail.default')!=='smtp') return back()->withErrors(['recipient'=>'SMTP is not selected. Log driver does not deliver real emails.']);
  try {
   Mail::raw('This message confirms that the configured SMTP transport accepted a PteroHub test email.',function($m) use($data) {
    $m->to($data['recipient'])->subject('Email delivery test');
    if (config('mail.reply_to.address')) $m->replyTo(config('mail.reply_to.address'));
   });
   Log::info('PteroHub SMTP test submitted',['admin_id'=>auth()->id()]);
   return back()->with('ok','SMTP accepted the test message. Confirm receipt and check spam folder.');
  } catch(Throwable $e) {
   Log::warning('PteroHub SMTP test failed',['class'=>get_class($e)]);
   return back()->withErrors(['recipient'=>'SMTP delivery failed. Check SMTP hostname, credentials and server logs.']);
  }
 }
}
