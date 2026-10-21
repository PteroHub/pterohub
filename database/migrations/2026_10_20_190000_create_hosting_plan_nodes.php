<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('hosting_plan_nodes', function(Blueprint $table) {
   $table->id();
   $table->foreignId('hosting_plan_id')->constrained('hosting_plans')->cascadeOnDelete();
   $table->foreignId('pterodactyl_node_id')->constrained('pterodactyl_nodes')->cascadeOnDelete();
   $table->timestamps();
   $table->unique(['hosting_plan_id','pterodactyl_node_id'],'hosting_plan_node_unique');
  });
 }
 public function down(): void { Schema::dropIfExists('hosting_plan_nodes'); }
};
