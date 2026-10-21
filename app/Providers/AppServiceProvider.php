<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Independent limits: GET page views never consume a POST diagnostic budget.
        // API cooldown and 60-second allocation cache are handled separately by
        // PterodactylReadOnlyDiagnostics, which remains read-only.
        // Separate budgets for SMTP settings and sending test messages.
        // A page refresh or other admin activity cannot consume either budget.
        RateLimiter::for('ph-email-save', function (Request $request) {
            return Limit::perMinute(20)->by('ph-email-save:'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
        RateLimiter::for('ph-email-test', function (Request $request) {
            $identity = $request->user()?->getAuthIdentifier() ?? $request->ip();
            return [
                Limit::perMinute(6)->by('ph-email-test-minute:'.$identity),
                Limit::perHour(30)->by('ph-email-test-hour:'.$identity),
            ];
        });
        RateLimiter::for('ph-pterodactyl-connection', function (Request $request) {
            return Limit::perMinute(30)->by('ph-connection:'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
        RateLimiter::for('ph-pterodactyl-allocations', function (Request $request) {
            return Limit::perMinute(30)->by('ph-allocations:'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
        RateLimiter::for('ph-pterodactyl-capacity', function (Request $request) {
            return Limit::perMinute(20)->by('ph-capacity:'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
    }
}
