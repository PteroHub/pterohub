<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('games', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('slug')->unique(); $t->string('engine')->nullable();
            $t->text('description')->nullable(); $t->boolean('active')->default(false); $t->unsignedInteger('sort_order')->default(0); $t->timestamps();
        });
        Schema::create('pterodactyl_eggs', function (Blueprint $t) {
            $t->id(); $t->foreignId('game_id')->nullable()->constrained('games')->nullOnDelete();
            $t->unsignedBigInteger('remote_id')->unique(); $t->string('name'); $t->string('docker_image')->nullable();
            $t->longText('startup')->nullable(); $t->json('environment_schema')->nullable(); $t->json('remote_payload')->nullable();
            $t->boolean('enabled_for_sale')->default(false); $t->timestamp('last_synced_at')->nullable(); $t->timestamps();
        });
        Schema::create('pterodactyl_nodes', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('remote_id')->unique(); $t->string('name'); $t->string('fqdn')->nullable();
            $t->unsignedBigInteger('remote_location_id')->nullable(); $t->boolean('enabled_for_sale')->default(false);
            $t->json('remote_payload')->nullable(); $t->timestamp('last_synced_at')->nullable(); $t->timestamps();
        });
        Schema::create('hosting_plans', function (Blueprint $t) {
            $t->id(); $t->foreignId('game_id')->constrained()->restrictOnDelete();
            $t->foreignId('pterodactyl_egg_id')->constrained('pterodactyl_eggs')->restrictOnDelete();
            $t->string('name'); $t->string('slug')->unique(); $t->unsignedInteger('memory_mb');
            $t->unsignedInteger('disk_mb'); $t->unsignedInteger('cpu_percent'); $t->unsignedSmallInteger('swap_mb')->default(0);
            $t->unsignedSmallInteger('allocations')->default(1); $t->unsignedSmallInteger('backups')->default(0);
            $t->unsignedSmallInteger('databases')->default(0); $t->json('startup_overrides')->nullable();
            $t->boolean('active')->default(false); $t->timestamps();
        });
        Schema::create('plan_prices', function (Blueprint $t) {
            $t->id(); $t->foreignId('hosting_plan_id')->constrained()->cascadeOnDelete();
            $t->string('currency', 3)->default('EUR'); $t->unsignedSmallInteger('billing_months');
            $t->unsignedBigInteger('amount_minor'); $t->unsignedBigInteger('setup_minor')->default(0);
            $t->unique(['hosting_plan_id','currency','billing_months']); $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->restrictOnDelete(); $t->string('status')->default('pending');
            $t->string('currency', 3)->default('EUR'); $t->unsignedBigInteger('total_minor')->default(0);
            $t->string('reference')->unique(); $t->timestamp('paid_at')->nullable(); $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('hosting_plan_id')->constrained()->restrictOnDelete();
            $t->foreignId('pterodactyl_node_id')->nullable()->constrained('pterodactyl_nodes')->nullOnDelete();
            $t->unsignedSmallInteger('billing_months'); $t->unsignedBigInteger('amount_minor');
            $t->json('configuration')->nullable(); $t->timestamps();
        });
        Schema::create('hosting_services', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('order_item_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('hosting_plan_id')->constrained()->restrictOnDelete();
            $t->foreignId('pterodactyl_node_id')->nullable()->constrained('pterodactyl_nodes')->nullOnDelete();
            $t->unsignedBigInteger('remote_server_id')->nullable()->unique(); $t->string('remote_server_uuid')->nullable()->unique();
            $t->string('status')->default('pending'); $t->timestamp('next_due_at')->nullable();
            $t->timestamp('suspended_at')->nullable(); $t->timestamp('terminated_at')->nullable();
            $t->timestamp('provisioned_at')->nullable(); $t->text('last_provision_error')->nullable(); $t->timestamps();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('number')->nullable()->unique(); $t->string('status')->default('draft');
            $t->string('currency', 3)->default('EUR'); $t->unsignedBigInteger('subtotal_minor')->default(0);
            $t->unsignedBigInteger('tax_minor')->default(0); $t->unsignedBigInteger('total_minor')->default(0);
            $t->date('issued_on')->nullable(); $t->date('due_on')->nullable(); $t->timestamp('paid_at')->nullable();
            $t->json('customer_snapshot')->nullable(); $t->timestamps();
        });
        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->string('description'); $t->unsignedInteger('quantity')->default(1); $t->unsignedBigInteger('unit_price_minor');
            $t->unsignedBigInteger('tax_minor')->default(0); $t->unsignedBigInteger('total_minor'); $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id(); $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->string('provider'); $t->string('provider_payment_id')->nullable(); $t->string('status')->default('pending');
            $t->string('currency', 3)->default('EUR'); $t->unsignedBigInteger('amount_minor');
            $t->timestamp('confirmed_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
            $t->unique(['provider','provider_payment_id']);
        });
        Schema::create('payment_webhook_events', function (Blueprint $t) {
            $t->id(); $t->string('provider'); $t->string('event_id'); $t->string('status')->default('received');
            $t->json('payload')->nullable(); $t->timestamp('processed_at')->nullable(); $t->timestamps();
            $t->unique(['provider','event_id']);
        });
        Schema::create('tickets', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('hosting_service_id')->nullable()->constrained()->nullOnDelete();
            $t->string('subject'); $t->string('department')->default('support'); $t->string('priority')->default('normal');
            $t->string('status')->default('open'); $t->timestamps();
        });
        Schema::create('ticket_messages', function (Blueprint $t) {
            $t->id(); $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete(); $t->longText('body');
            $t->boolean('is_internal')->default(false); $t->timestamps();
        });
        Schema::create('provisioning_attempts', function (Blueprint $t) {
            $t->id(); $t->foreignId('hosting_service_id')->constrained()->cascadeOnDelete();
            $t->string('action'); $t->string('status')->default('pending');
            $t->uuid('idempotency_key')->unique(); $t->text('error')->nullable();
            $t->timestamp('finished_at')->nullable(); $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['provisioning_attempts','ticket_messages','tickets','payment_webhook_events','payments',
            'invoice_items','invoices','hosting_services','order_items','orders','plan_prices',
            'hosting_plans','pterodactyl_nodes','pterodactyl_eggs','games'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
