<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('users', function(Blueprint $table) {
   $table->string('username',32)->nullable()->unique();
   $table->string('first_name',150)->nullable();
   $table->string('last_name',150)->nullable();
  });
 }
 public function down(): void {
  Schema::table('users',function(Blueprint $table){
   $table->dropUnique(['username']);
   $table->dropColumn(['username','first_name','last_name']);
  });
 }
};
