<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/** Beta read-only integrity checks. No API requests, no database mutations. */
Artisan::command('pterohub:beta-audit', function () {
    $db = \Illuminate\Support\Facades\DB::class;
    $schema = \Illuminate\Support\Facades\Schema::class;
    $tables = [
        'users', 'orders', 'order_items', 'hosting_services', 'hosting_plans',
        'pterodactyl_nodes', 'pterohub_pterodactyl_user_links',
        'provisioning_attempts', 'invoices', 'payments',
        'pterohub_service_checks', 'pterohub_service_events',
    ];
    $problems = [];
    foreach ($tables as $table) {
        if (!$schema::hasTable($table)) $problems[] = 'Required table missing: '.$table;
    }
    if ($problems) {
        foreach ($problems as $problem) $this->error($problem);
        return 1;
    }
    $checks = [
        'Service customer differs from order customer' => $db::table('hosting_services as s')
            ->join('order_items as oi', 'oi.id', '=', 's.order_item_id')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereColumn('s.user_id', '!=', 'o.user_id')->count(),
        'Service plan differs from ordered plan' => $db::table('hosting_services as s')
            ->join('order_items as oi', 'oi.id', '=', 's.order_item_id')
            ->whereColumn('s.hosting_plan_id', '!=', 'oi.hosting_plan_id')->count(),
        'Provisioned service without remote identity' => $db::table('hosting_services')
            ->whereIn('status', ['provisioned','active'])
            ->where(function ($q) { $q->whereNull('remote_server_id')->orWhereNull('remote_server_uuid'); })->count(),
        'Service has remote ID but no UUID' => $db::table('hosting_services')
            ->whereNotNull('remote_server_id')->whereNull('remote_server_uuid')->count(),
        'Service has remote UUID but no ID' => $db::table('hosting_services')
            ->whereNull('remote_server_id')->whereNotNull('remote_server_uuid')->count(),
        'Linked user email differs from customer' => $db::table('pterohub_pterodactyl_user_links as l')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->whereRaw('LOWER(TRIM(l.remote_email)) <> LOWER(TRIM(u.email))')->count(),
        'Pending provisioning attempt older than 30 minutes' => $db::table('provisioning_attempts')
            ->where('status','pending')->where('created_at','<',now()->subMinutes(30))->count(),
        'Review service without provisioning attempts' => $db::table('hosting_services as s')
            ->where('s.status','provisioning_review')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('provisioning_attempts as a')
                    ->whereColumn('a.hosting_service_id','s.id');
            })->count(),
        'Paid invoice without paid timestamp' => $db::table('invoices')
            ->where('status','paid')->whereNull('paid_at')->count(),
    ];
    // Beta 1.20: check commercial planning and account/notification consistency.
    if ($schema::hasTable('pterohub_renewal_plans')) {
        $checks['Active renewal plan with terminated service'] = $db::table('pterohub_renewal_plans as r')
            ->join('hosting_services as s','s.id','=','r.hosting_service_id')
            ->where('r.status','planned')->whereIn('s.status',['terminated','cancelled'])->count();
        $checks['Renewal plan with unexpected status'] = $db::table('pterohub_renewal_plans')
            ->whereNotIn('status',['planned','cancelled'])->count();
    }
    if ($schema::hasTable('pterohub_account_sync')) {
        $checks['Account sync marked synced without linked Pterodactyl user'] = $db::table('pterohub_account_sync as a')
            ->leftJoin('pterohub_pterodactyl_user_links as l','l.user_id','=','a.user_id')
            ->where('a.status','synced')->whereNull('l.user_id')->count();
    }
    if ($schema::hasTable('pterohub_notification_deliveries')) {
        $checks['Notification delivery marked sent with zero attempts'] = $db::table('pterohub_notification_deliveries')
            ->where('status','sent')->where('attempts','<',1)->count();
    }
    foreach ($checks as $label => $count) {
        $count = (int) $count;
        if ($count) { $problems[] = $label.': '.$count; $this->warn($label.': '.$count); }
        else $this->info('OK: '.$label);
    }
    $this->line('Read-only audit completed. No server actions, payments or database changes.');
    if ($problems) {
        $this->warn('Review '.count($problems).' finding(s) before moving to production.');
        return 1;
    }
    $this->info('All structural checks passed. This does not prove remote server health or complete application security.');
    return 0;
})->purpose('Run read-only PteroHub Beta database consistency checks');
