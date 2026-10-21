<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('pterohub_account_sync',function(Blueprint $t){
   $t->id();$t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
   $t->string('status',32)->default('pending');$t->string('action',32)->nullable();
   $t->unsignedBigInteger('remote_user_id')->nullable();$t->string('error_code',100)->nullable();
   $t->unsignedInteger('attempts')->default(0);$t->timestamp('last_attempt_at')->nullable();$t->timestamps();
  });
  Schema::create('pterohub_pending_emails',function(Blueprint $t){
   $t->id();$t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
   $t->string('new_email',254);$t->string('token_hash',64);$t->timestamp('expires_at');$t->timestamps();
  });
 }
 public function down(): void {Schema::dropIfExists('pterohub_pending_emails');Schema::dropIfExists('pterohub_account_sync');}
};
