<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('pterohub_pterodactyl_user_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $t->unsignedBigInteger('remote_user_id')->unique();
            $t->string('remote_email');
            $t->foreignId('linked_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('pterohub_pterodactyl_user_links'); }
};
