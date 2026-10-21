<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('pterohub_mail_settings', function (Blueprint $t) {
   $t->unsignedTinyInteger('id')->primary();
   $t->string('driver', 16)->default('log');
   $t->string('host', 255)->nullable();
   $t->unsignedSmallInteger('port')->nullable();
   $t->string('scheme', 16)->nullable();
   $t->string('username', 254)->nullable();
   $t->text('encrypted_password')->nullable();
   $t->string('from_address', 254)->nullable();
   $t->string('reply_to', 254)->nullable();
   $t->timestamps();
  });
 }
 public function down(): void {Schema::dropIfExists('pterohub_mail_settings');}
};
