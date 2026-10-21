<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('pterohub_extensions', function (Blueprint $t) {
   $t->id(); $t->string('slug')->unique(); $t->string('name');
   $t->string('type',32); $t->string('version',40); $t->string('provider')->nullable();
   $t->json('manifest'); $t->boolean('enabled')->default(false);
   $t->string('status',32)->default('registered'); $t->timestamps();
  });
 }
 public function down(): void { Schema::dropIfExists('pterohub_extensions'); }
};
