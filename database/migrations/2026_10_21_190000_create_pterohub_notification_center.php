<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('pterohub_notification_settings', function(Blueprint $t){
   $t->string('event_type',48)->primary(); $t->boolean('enabled')->default(false); $t->timestamps();
  });
  Schema::create('pterohub_notification_deliveries',function(Blueprint $t){
   $t->id(); $t->string('event_key',160); $t->string('event_type',48);
   $t->unsignedBigInteger('user_id')->nullable(); $t->string('recipient',254);
   $t->string('subject',255); $t->string('status',24)->default('pending');
   $t->unsignedSmallInteger('attempts')->default(0); $t->timestamp('last_attempt_at')->nullable();
   $t->timestamp('sent_at')->nullable(); $t->string('failure_class',160)->nullable();
   $t->timestamps(); $t->unique(['event_key','recipient'],'ph_notify_event_recipient_unique');
   $t->index(['status','created_at'],'ph_notify_status_created');
  });
 }
 public function down(): void {Schema::dropIfExists('pterohub_notification_deliveries'); Schema::dropIfExists('pterohub_notification_settings');}
};
