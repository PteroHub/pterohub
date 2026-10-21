<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;
use Throwable;
final class PterohubMailSettings {
 public static function record(): ?object {
  try {
   if (!Schema::hasTable('pterohub_mail_settings')) return null;
   return DB::table('pterohub_mail_settings')->where('id', 1)->first();
  } catch (Throwable $e) {report($e); return null;}
 }
 public static function apply(): void {
  $s=self::record(); if (!$s) return;
  if (!in_array($s->driver,['log','smtp'],true)) return;
  $changes=['mail.default'=>$s->driver];
  if ($s->driver==='smtp') {
   if (!$s->host || !$s->port || !$s->from_address) return;
   $changes['mail.mailers.smtp.host']=$s->host;
   $changes['mail.mailers.smtp.port']=(int)$s->port;
   $changes['mail.mailers.smtp.scheme']=$s->scheme ?: null;
   $changes['mail.mailers.smtp.username']=$s->username ?: null;
   try {$changes['mail.mailers.smtp.password']=$s->encrypted_password ? Crypt::decryptString($s->encrypted_password) : null;}
   catch(Throwable $e) {report($e); return;}
  }
  if ($s->from_address) $changes['mail.from.address']=$s->from_address;
  if ($s->reply_to) $changes['mail.reply_to.address']=$s->reply_to;
  config($changes);
 }
}
