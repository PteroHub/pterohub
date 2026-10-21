<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pterohub_service_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hosting_service_id')->constrained('hosting_services')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('result', 40);
            $table->unsignedBigInteger('remote_server_id')->nullable();
            $table->timestamps();
            $table->index(['hosting_service_id', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('pterohub_service_checks'); }
};
