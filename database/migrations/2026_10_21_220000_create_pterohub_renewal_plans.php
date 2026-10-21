<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('pterohub_renewal_plans',function(Blueprint $t){
   $t->id(); $t->foreignId('hosting_service_id')->unique()->constrained('hosting_services')->restrictOnDelete();
   $t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
   $t->date('planned_for'); $t->string('status',16)->default('planned');
   $t->string('note',500)->nullable(); $t->timestamps();
   $t->index(['status','planned_for']);
  });
  Schema::create('pterohub_renewal_plan_events',function(Blueprint $t){
   $t->id(); $t->foreignId('hosting_service_id')->constrained('hosting_services')->restrictOnDelete();
   $t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
   $t->string('action',24); $t->date('planned_for')->nullable(); $t->timestamp('created_at')->useCurrent();
   $t->index(['hosting_service_id','created_at']);
  });
 }
 public function down(): void { Schema::dropIfExists('pterohub_renewal_plan_events');Schema::dropIfExists('pterohub_renewal_plans'); }
};
