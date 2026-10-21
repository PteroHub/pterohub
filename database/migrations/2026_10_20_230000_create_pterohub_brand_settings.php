<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('pterohub_brand_settings', function(Blueprint $table) { $table->id(); $table->string('company_name',120)->nullable(); $table->string('sender_name',120)->nullable(); $table->string('reply_to_email',254)->nullable(); $table->timestamps(); }); }
 public function down(): void { Schema::dropIfExists('pterohub_brand_settings'); }
};
