<?php

namespace App\Http\Controllers;

use App\Support\PterohubSystemSettings as Settings;
use App\Support\PterohubPterodactylConfig as ApiConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

final class PterohubSettingsController extends Controller
{
    private const GROUPS = [
        'general' => ['title' => 'General', 'description' => 'Public-facing platform defaults.', 'fields' => [
            'default_currency' => ['label' => 'Default currency', 'type' => 'select', 'options' => ['EUR'=>'EUR', 'USD'=>'USD', 'GBP'=>'GBP']],
            'support_email' => ['label' => 'Support contact email', 'type' => 'email'],
            'timezone' => ['label' => 'Display timezone', 'type' => 'text'],
        ]],
        'captcha' => ['title' => 'Captcha & Security', 'description' => 'Credentials are stored but enforcement is not enabled until the authentication integration is tested.', 'fields' => [
            'captcha_provider' => ['label' => 'Captcha provider', 'type' => 'select', 'options' => ['disabled'=>'Disabled', 'turnstile'=>'Cloudflare Turnstile', 'recaptcha'=>'Google reCAPTCHA v2 checkbox']],
            'turnstile_site_key' => ['label' => 'Turnstile site key', 'type' => 'text'],
            'turnstile_secret' => ['label' => 'Turnstile secret', 'type' => 'secret'],
            'recaptcha_site_key' => ['label' => 'reCAPTCHA site key', 'type' => 'text'],
            'recaptcha_secret' => ['label' => 'reCAPTCHA secret', 'type' => 'secret'],
        ]],
        'integrations' => ['title' => 'API & Integrations', 'description' => 'Save credentials for upcoming integrations. These do not activate external API calls yet.', 'fields' => [
            'google_api_key' => ['label' => 'Google API key', 'type' => 'secret'],
            'cloudflare_api_token' => ['label' => 'Cloudflare API token', 'type' => 'secret'],
            'discord_client_id' => ['label' => 'Discord client ID', 'type' => 'text'],
            'discord_client_secret' => ['label' => 'Discord client secret', 'type' => 'secret'],
            'steam_api_key' => ['label' => 'Steam Web API key (optional)', 'type' => 'secret'],
        ]],
        'pterodactyl' => ['title' => 'Pterodactyl Connection', 'description' => 'Application API credentials used for read-only node and Egg synchronization and diagnostics. Saving does not enable provisioning.', 'fields' => [
            'pterodactyl_url' => ['label' => 'Pterodactyl panel URL (HTTPS)', 'type' => 'url'],
            'pterodactyl_api_key' => ['label' => 'Application API key', 'type' => 'secret'],
        ]],
        'payments' => ['title' => 'Payments', 'description' => 'Stripe and PayPal are NOT enabled for charging. Configure credentials for future integration.', 'fields' => [
            'payment_mode' => ['label' => 'Integration mode', 'type' => 'select', 'options' => ['sandbox'=>'Sandbox', 'live'=>'Live (not activated)']],
            'stripe_publishable_key' => ['label' => 'Stripe publishable key', 'type' => 'text'],
            'stripe_secret_key' => ['label' => 'Stripe secret key', 'type' => 'secret'],
            'stripe_webhook_secret' => ['label' => 'Stripe webhook signing secret', 'type' => 'secret'],
            'paypal_client_id' => ['label' => 'PayPal client ID', 'type' => 'text'],
            'paypal_client_secret' => ['label' => 'PayPal client secret', 'type' => 'secret'],
            'paypal_webhook_id' => ['label' => 'PayPal webhook ID', 'type' => 'text'],
        ]],
        'hosting' => ['title' => 'Hosting & Provisioning', 'description' => 'Safety controls are informational until provisioning workflows are implemented.', 'fields' => [
            'default_server_region' => ['label' => 'Preferred region label', 'type' => 'text'],
            'provisioning_policy' => ['label' => 'Provisioning policy', 'type' => 'select', 'options' => ['disabled'=>'Disabled', 'manual'=>'Manual review']],
        ]],
        'portal' => ['title' => 'Customer Portal', 'description' => 'Portal configuration. DNS, HTTPS and application routing must be configured separately.', 'fields' => [
            'client_portal_url' => ['label' => 'Customer portal URL', 'type' => 'url'],
            'portal_registration' => ['label' => 'Registration policy (future)', 'type' => 'select', 'options' => ['disabled'=>'Disabled', 'enabled'=>'Enabled (not applied automatically)']],
        ]],
    ];

    private function guard(): void
    {
        abort_unless(auth()->check() && (bool) auth()->user()->is_pterohub_admin, 403);
    }

    public function index()
    {
        $this->guard();
        return view('pterohub.settings.index');
    }

    public function show(string $group)
    {
        $this->guard();
        abort_unless(isset(self::GROUPS[$group]), 404);
        abort_unless(Schema::hasTable('pterohub_system_settings'), 503, 'Run migrations first.');
        $definition = self::GROUPS[$group];
        $values = [];
        foreach ($definition['fields'] as $key => $field) {
            $values[$key] = $field['type'] === 'secret' ? '' : ($key === 'pterodactyl_url' ? ApiConfig::url() : Settings::get($key, ''));
            $definition['fields'][$key]['configured'] = $field['type'] === 'secret' && (Settings::configured($key) || ($key === 'pterodactyl_api_key' && ApiConfig::apiKey() !== ''));
        }
        return view('pterohub.settings.group', compact('group', 'definition', 'values'));
    }

    public function update(Request $request, string $group)
    {
        $this->guard();
        abort_unless(isset(self::GROUPS[$group]), 404);
        abort_unless(Schema::hasTable('pterohub_system_settings'), 503, 'Run migrations first.');
        $definition = self::GROUPS[$group];
        $rules = [];
        foreach ($definition['fields'] as $key => $field) {
            $rules[$key] = match ($field['type']) {
                'select' => ['nullable', Rule::in(array_keys($field['options']))],
                'email' => ['nullable', 'email', 'max:254'],
                'url' => $key === 'pterodactyl_url' ? ['nullable', 'url:https', 'max:255', function ($attribute, $value, $fail) {
                    if ($value !== null && $value !== '') {
                        $parts = parse_url($value);
                        if (!$parts || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || isset($parts['port']) && ($parts['port'] < 1 || $parts['port'] > 65535) || !empty($parts['path']) && $parts['path'] !== '/') {
                            $fail('Enter the HTTPS panel origin only, without a path, username, query or fragment.');
                        }
                    }
                }] : ['nullable', 'url:http,https', 'max:255'],
                default => ['nullable', 'string', 'max:2048'],
            };
        }
        $validated = $request->validate($rules);
        DB::transaction(function () use ($validated, $definition) {
            foreach ($definition['fields'] as $key => $field) {
                $value = trim((string) ($validated[$key] ?? ''));
                if ($field['type'] === 'secret' && $value === '') continue; // preserve encrypted secret
                Settings::put($key, $value !== '' ? $value : null, $field['type'] === 'secret');
            }
        });
        return redirect()->route('pterohub.settings.group', $group)->with('ok', 'Settings saved. Integrations require activation and testing before use.');
    }
}
