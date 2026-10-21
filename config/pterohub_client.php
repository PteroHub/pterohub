<?php
return [
    'registration_enabled' => env('PH_CLIENT_REGISTRATION_ENABLED', false),
    'turnstile_site_key' => env('PH_TURNSTILE_SITE_KEY', ''),
    'turnstile_secret' => env('PH_TURNSTILE_SECRET', ''),
];
