<?php
namespace App\Services;

use App\Models\User;
use App\Support\PterohubPterodactylConfig as Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** PteroHub is authoritative. No password sharing, server writes, or automatic account claiming. */
final class PterohubAccountSync {
 private function call(string $method,string $path,array $data=[]): array {
  if(!Config::configured()) throw new RuntimeException('api_not_configured');
  $req=Http::withToken(Config::apiKey())->accept('application/vnd.pterodactyl.v1+json')
   ->timeout(20)->withOptions(['allow_redirects'=>false]);
  // Use Laravel's explicit JSON request mode for Pterodactyl Application API writes.
  // It sets Content-Type: application/json, as with PendingRequest::patch().
  if($method!=='GET') $req=$req->asJson();
  $res=$req->send($method,Config::url().'/api/application/'.$path,
   $method==='GET'?['query'=>$data]:['json'=>$data]);
  if(!$res->successful()) {
   $reason='remote_http_'.$res->status();
   // Read only structured error codes and known, non-sensitive field names.
   // Never persist or display remote detail messages, rejected values or raw bodies.
   if($res->status()===422) {
    $allowedFields=['email','username','first_name','last_name','name_first','name_last',
     'language','password','external_id','root_admin'];
    $errors=$res->json('errors',[]);
    if(is_array($errors)) {
     foreach($errors as $error) {
      if(!is_array($error)) continue;
      $field=data_get($error,'meta.source_field');
      if(!is_string($field)) $field=data_get($error,'meta.field');
      if(is_string($field)&&in_array($field,$allowedFields,true)) {
       $reason.='_field_'.$field;
       break;
      }
     }
    }
   }
   throw new RuntimeException($reason);
  }
  $json=$res->json();if(!is_array($json)) throw new RuntimeException('invalid_remote_response');
  return $json;
 }
 private function attributes(array $data): array {
  $a=$data['attributes']??data_get($data,'data.attributes');
  if(!is_array($a)||!is_numeric($a['id']??null)||empty($a['email'])) throw new RuntimeException('invalid_remote_identity');
  return $a;
 }
 private function parts(string $name): array {
  $name=trim(preg_replace('/\s+/u',' ',$name));
  $pieces=preg_split('/\s+/u',$name,2);
  return [Str::limit($pieces[0]??'Customer',190,''),Str::limit($pieces[1]??'Customer',190,'')];
 }
 private function state(int $userId,string $status,string $action,?int $remoteId=null,?string $error=null): void {
  if(!Schema::hasTable('pterohub_account_sync')) return;
  $row=DB::table('pterohub_account_sync')->where('user_id',$userId)->first();
  $values=['status'=>$status,'action'=>$action,'remote_user_id'=>$remoteId,'error_code'=>$error,
   'last_attempt_at'=>now(),'updated_at'=>now()];
  if($row) DB::table('pterohub_account_sync')->where('user_id',$userId)->update($values+['attempts'=>($row->attempts+1)]);
  else DB::table('pterohub_account_sync')->insert($values+['user_id'=>$userId,'attempts'=>1,'created_at'=>now()]);
 }
 /** No automatic claiming by email. If a remote email exists, request administrator review. */
 private function emailConflict(string $email): bool {
  $data=$this->call('GET','users',['filter'=>['email'=>$email],'per_page'=>100]);
  foreach($data['data']??[] as $row) {
   $a=$row['attributes']??[];
   if(is_string($a['email']??null)&&strcasecmp($a['email'],$email)===0) return true;
  }
  return false;
 }
 /** Called only after email verification or by explicit admin retry. Never re-POST an uncertain creation. */
 public function sync(int $userId,?string $name=null,?string $email=null,?string $username=null,?string $firstName=null,?string $lastName=null): array {
  if(!Schema::hasTable('pterohub_account_sync')) return ['status'=>'failed','error'=>'migration_required'];
  $lock=Cache::lock('ph-account-sync-'.$userId,30);
  if(!$lock->get()) return ['status'=>'pending','error'=>'sync_in_progress'];
  try {
   $user=User::find($userId);
   if(!$user||$user->is_pterohub_admin) return ['status'=>'failed','error'=>'invalid_customer'];
   if(!$user->hasVerifiedEmail()) return ['status'=>'pending','error'=>'email_not_verified'];
   $name=$name??$user->name;$email=Str::lower(trim($email??$user->email));
   [$defaultFirst,$defaultLast]=$this->parts($name);
   $first=$firstName??$user->first_name??$defaultFirst;
   $last=$lastName??$user->last_name??$defaultLast;
   $first=trim($first);$last=trim($last);
   if($first===''||$last==='') throw new RuntimeException('invalid_name');
   $link=DB::table('pterohub_pterodactyl_user_links')->where('user_id',$userId)->first();
   $existing=DB::table('pterohub_account_sync')->where('user_id',$userId)->first();
   if(!$link&&$existing&&$existing->status==='review') return ['status'=>'review','error'=>'manual_review_required'];
   if($link) {
    $remote=$this->attributes($this->call('GET','users/'.$link->remote_user_id));
    if((int)$remote['id']!==(int)$link->remote_user_id) throw new RuntimeException('remote_identity_mismatch');
    // Update only the verified linked user. Do not update password, admin flags or server ownership.
    $updated=$this->attributes($this->call('PATCH','users/'.$link->remote_user_id,[
     'email'=>$email,'username'=>$username??$user->username??(string)($remote['username']??''),
     'first_name'=>$first,'last_name'=>$last,'language'=>(string)($remote['language']??'en'),
    ]));
    if((int)$updated['id']!==(int)$link->remote_user_id||strcasecmp($updated['email'],$email)!==0
     ||strcasecmp((string)($updated['username']??''),(string)($username??$user->username??$remote['username']??''))!==0)
     throw new RuntimeException('remote_update_not_confirmed');
    DB::table('pterohub_pterodactyl_user_links')->where('user_id',$userId)->update(['remote_email'=>$email,'updated_at'=>now()]);
    // Persist the remote username only after Pterodactyl confirms the update.
    if($user->username===null && $username===null) {
     $candidate=(string)($updated['username']??'');
     if($candidate!=='' && !DB::table('users')->where('username',$candidate)->where('id','!=',$userId)->exists()) {
      DB::table('users')->where('id',$userId)->update(['username'=>$candidate]);
     }
    }
    $this->state($userId,'synced','update',(int)$link->remote_user_id);
    return ['status'=>'synced','remote_user_id'=>(int)$link->remote_user_id];
   }
   if($this->emailConflict($email)) {
    $this->state($userId,'review','create',null,'remote_email_exists_manual_link_required');
    return ['status'=>'review','error'=>'remote_email_exists_manual_link_required'];
   }
   // A failed/uncertain POST must never be retried blindly. Mark review before attempting POST.
   $this->state($userId,'review','create',null,'remote_create_in_progress_or_uncertain');
   $username=$username??$user->username??'phu'.$userId;
   $created=$this->attributes($this->call('POST','users',[
    'email'=>$email,'username'=>$username,'first_name'=>$first,'last_name'=>$last,
    'password'=>Str::random(48),
   ]));
   if(strcasecmp($created['email'],$email)!==0 || strcasecmp((string)($created['username']??''),$username)!==0) throw new RuntimeException('remote_create_identity_mismatch');
   DB::table('pterohub_pterodactyl_user_links')->insert([
    'user_id'=>$userId,'remote_user_id'=>(int)$created['id'],'remote_email'=>$email,
    'linked_by'=>$userId,'created_at'=>now(),'updated_at'=>now(),
   ]);
   $this->state($userId,'synced','create',(int)$created['id']);
   return ['status'=>'synced','remote_user_id'=>(int)$created['id']];
  } catch(Throwable $e) {
   $code=$e instanceof RuntimeException?$e->getMessage():'connection_or_internal_error';
   $code=preg_match('/^[a-z0-9_]+$/',$code)?substr($code,0,100):'connection_or_internal_error';
   $previous=DB::table('pterohub_account_sync')->where('user_id',$userId)->first();
   $status=$previous&&$previous->status==='review'?'review':'failed';
   $this->state($userId,$status,$previous->action??'sync',isset($previous->remote_user_id)?(int)$previous->remote_user_id:null,$code);
   Log::warning('PteroHub account sync unsuccessful',['user_id'=>$userId,'reason'=>$code]);
   return ['status'=>$status,'error'=>$code];
  } finally {$lock->release();}
 }
 /** For an already linked user, fail closed if remote update cannot be confirmed. */
 public function changeAccount(int $userId,string $first,string $last,string $email,string $username): array {
  $user=User::findOrFail($userId);
  $first=trim($first);$last=trim($last);$username=trim($username);
  $email=Str::lower(trim($email));
  $name=$first.' '.$last;
  $linked=DB::table('pterohub_pterodactyl_user_links')->where('user_id',$userId)->exists();
  if($linked){
   $result=$this->sync($userId,$name,$email,$username,$first,$last);
   if($result['status']!=='synced') return $result;
  }
  $user->name=$name;$user->first_name=$first;$user->last_name=$last;
  $user->username=$username;$user->email=$email;$user->save();
  if(!$linked) return $this->sync($userId);
  return ['status'=>'synced'];
 }
}
