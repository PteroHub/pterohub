<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('pterohub_service_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hosting_service_id')->constrained('hosting_services')->restrictOnDelete();
            $t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('event_type', 32);
            $t->string('result', 40);
            $t->unsignedBigInteger('remote_server_id')->nullable();
            $t->string('installation', 40)->nullable();
            $t->boolean('remote_suspended')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['hosting_service_id','created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('pterohub_service_events'); }
};
