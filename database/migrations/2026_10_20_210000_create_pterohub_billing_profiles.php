<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('pterohub_billing_profiles',function(Blueprint $t){
   $t->id();$t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
   $t->string('customer_type',16)->default('individual');
   $t->string('legal_name',180)->nullable();$t->string('company',180)->nullable();
   $t->string('tax_id',40)->nullable();$t->string('vat_number',40)->nullable();
   $t->string('address_line1',200)->nullable();$t->string('address_line2',200)->nullable();
   $t->string('city',120)->nullable();$t->string('region',120)->nullable();
   $t->string('postal_code',32)->nullable();$t->char('country',2)->nullable();
   $t->string('pec_email',200)->nullable();$t->string('recipient_code',20)->nullable();
   $t->timestamps();
  });
 }
 public function down(): void {Schema::dropIfExists('pterohub_billing_profiles');}
};
