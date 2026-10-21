<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  // MySQL/MariaDB may retain the table after a failed CREATE TABLE index statement.
  // Recover that partial installation without deleting any payment attempt records.
  if (!Schema::hasTable('pterohub_sandbox_payment_attempts')) {
   Schema::create('pterohub_sandbox_payment_attempts', function (Blueprint $t) {
    $t->id();
    $t->foreignId('order_id')->constrained('orders')->restrictOnDelete();
    $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
    $t->string('provider', 16);
    $t->string('status', 32)->default('initiating');
    $t->string('provider_order_id', 255)->nullable();
    $t->string('provider_capture_id', 255)->nullable();
    $t->unsignedBigInteger('amount_minor');
    $t->string('currency', 3)->default('EUR');
    $t->string('failure_code', 120)->nullable();
    $t->timestamp('verified_at')->nullable();
    $t->timestamps();
   });
  }
  if (!Schema::hasIndex('pterohub_sandbox_payment_attempts', 'ph_sb_attempt_provider_order_uq')) {
   Schema::table('pterohub_sandbox_payment_attempts', function (Blueprint $t) {
    $t->unique(['provider', 'provider_order_id'], 'ph_sb_attempt_provider_order_uq');
   });
  }
  if (!Schema::hasIndex('pterohub_sandbox_payment_attempts', 'ph_sb_attempt_provider_capture_uq')) {
   Schema::table('pterohub_sandbox_payment_attempts', function (Blueprint $t) {
    $t->unique(['provider', 'provider_capture_id'], 'ph_sb_attempt_provider_capture_uq');
   });
  }
  if (!Schema::hasIndex('pterohub_sandbox_payment_attempts', 'ph_sb_attempt_order_status_idx')) {
   Schema::table('pterohub_sandbox_payment_attempts', function (Blueprint $t) {
    $t->index(['order_id', 'status'], 'ph_sb_attempt_order_status_idx');
   });
  }
  if (!Schema::hasTable('pterohub_sandbox_webhook_events')) {
   Schema::create('pterohub_sandbox_webhook_events', function (Blueprint $t) {
    $t->id();
    $t->string('provider', 16);
    $t->string('event_id', 255);
    $t->string('event_type', 100);
    $t->string('status', 32);
    $t->string('failure_code', 120)->nullable();
    $t->timestamps();
   });
  }
  if (!Schema::hasIndex('pterohub_sandbox_webhook_events', 'ph_sb_webhook_provider_event_uq')) {
   Schema::table('pterohub_sandbox_webhook_events', function (Blueprint $t) {
    $t->unique(['provider', 'event_id'], 'ph_sb_webhook_provider_event_uq');
   });
  }
 }
 public function down(): void {
  Schema::dropIfExists('pterohub_sandbox_webhook_events');
  Schema::dropIfExists('pterohub_sandbox_payment_attempts');
 }
};
