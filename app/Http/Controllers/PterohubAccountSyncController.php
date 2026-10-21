<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\PterohubAccountSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class PterohubAccountSyncController extends Controller {
 private function customer(): User {
  abort_unless(auth()->check() && !auth()->user()->is_pterohub_admin,403);
  return auth()->user();
 }
 private function admin(): void {abort_unless(auth()->check() && (bool)auth()->user()->is_pterohub_admin,403);}
 public function profile(Request $request,PterohubAccountSync $sync) {
  $user=$this->customer();
  $data=$request->validate([
   'first_name'=>'required|string|min:1|max:150',
   'last_name'=>'required|string|min:1|max:150',
   'username'=>['required','string','min:3','max:32','regex:/^[A-Za-z0-9._-]+$/',Rule::unique('users','username')->ignore($user->id)],
  ]);
  $result=$sync->changeAccount($user->id,trim($data['first_name']),trim($data['last_name']),$user->email,trim($data['username']));
  if($result['status']!=='synced') return back()->withErrors(['username'=>'Pterodactyl synchronization did not complete: '.($result['error']??$result['status']).'. Account details were not changed.']);
  return back()->with('ok','Account name updated and synchronized.');
 }
 public function requestEmail(Request $request) {
  $user=$this->customer();
  $data=$request->validate(['email'=>['required','email:rfc','max:254',Rule::unique('users','email')->ignore($user->id)]]);
  $email=Str::lower(trim($data['email']));
  if(strcasecmp($email,$user->email)===0) return back()->withErrors(['email'=>'Enter a different email address.']);
  $token=Str::random(64);
  $url=URL::temporarySignedRoute('ph.client.account.email.confirm',now()->addMinutes(30),['token'=>$token]);
  // Do not persist a pending address if the verification email cannot be submitted.
  try {
   Mail::raw("A request was made to change your PteroHub account email. Confirm it within 30 minutes:\n\n".$url."\n\nIf you did not request this, ignore this message.",
    fn($message)=>$message->to($email)->subject('PteroHub: confirm your new email address'));
  } catch(\Throwable $e) {return back()->withErrors(['email'=>'Could not send the confirmation message. Check the SMTP settings.']);}
  DB::table('pterohub_pending_emails')->updateOrInsert(['user_id'=>$user->id],[
   'new_email'=>$email,'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addMinutes(30),'updated_at'=>now(),
   'created_at'=>now(),
  ]);
  return back()->with('ok','A confirmation link has been sent to the new email address. The current email remains unchanged.');
 }
 public function confirmEmail(Request $request,string $token,PterohubAccountSync $sync) {
  $user=$this->customer();
  $row=DB::table('pterohub_pending_emails')->where('user_id',$user->id)->first();
  abort_unless($request->hasValidSignature()&&$row&&now()->lessThan($row->expires_at)
   &&hash_equals($row->token_hash,hash('sha256',$token)),403);
  if(DB::table('users')->where('email',$row->new_email)->where('id','!=',$user->id)->exists())
   return redirect()->route('ph.client.profile')->withErrors(['email'=>'That email address is already registered.']);
  if(!$user->username) return redirect()->route('ph.client.profile')->withErrors(['email'=>'Please save your username in Account details before changing your email.']);
  $parts=preg_split('/\s+/u',trim($user->name),2);
  $result=$sync->changeAccount($user->id,$user->first_name??$parts[0],$user->last_name??($parts[1]??'Customer'),$row->new_email,$user->username);
  if($result['status']!=='synced') return redirect()->route('ph.client.profile')
   ->withErrors(['email'=>'Remote account update could not be confirmed: '.($result['error']??$result['status']).'. Your original email remains active.']);
  DB::table('pterohub_pending_emails')->where('user_id',$user->id)->delete();
  return redirect()->route('ph.client.profile')->with('ok','Email address verified and synchronized.');
 }
 public function adminSave(Request $request,int $id,PterohubAccountSync $sync) {
  $this->admin();$user=User::findOrFail($id);abort_if($user->is_pterohub_admin,403);
  $data=$request->validate([
   'first_name'=>'required|string|min:1|max:150',
   'last_name'=>'required|string|min:1|max:150',
   'username'=>['required','string','min:3','max:32','regex:/^[A-Za-z0-9._-]+$/',Rule::unique('users','username')->ignore($id)],
   'email'=>['required','email:rfc','max:254',Rule::unique('users','email')->ignore($id)],
  ]);
  $result=$sync->changeAccount($id,trim($data['first_name']),trim($data['last_name']),$data['email'],trim($data['username']));
  if($result['status']!=='synced') return back()->withErrors(['email'=>'Account was not fully synchronized: '.($result['error']??$result['status']).'. For linked accounts, local details were preserved.']);
  return back()->with('ok','Customer account updated and synchronized with Pterodactyl.');
 }
 public function adminRetry(int $id,PterohubAccountSync $sync) {
  $this->admin();$user=User::findOrFail($id);abort_if($user->is_pterohub_admin,403);
  $result=$sync->sync($id);
  if($result['status']!=='synced') return back()->withErrors(['sync'=>'Sync requires attention: '.($result['error']??$result['status'])]);
  return back()->with('ok','Pterodactyl account synchronized.');
 }
}
