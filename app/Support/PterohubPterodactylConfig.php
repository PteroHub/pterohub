<?php

namespace App\Support;

/** Centralized Pterodactyl Application API settings, admin-managed with legacy .env fallback. */
final class PterohubPterodactylConfig
{
    public static function url(): string
    {
        $saved = PterohubSystemSettings::get('pterodactyl_url');
        return rtrim(trim((string) ($saved ?: config('pterohub.pterodactyl.url', ''))), '/');
    }

    public static function apiKey(): string
    {
        $saved = PterohubSystemSettings::get('pterodactyl_api_key');
        return (string) ($saved ?: config('pterohub.pterodactyl.api_key', ''));
    }

    public static function configured(): bool
    {
        return str_starts_with(self::url(), 'https://') && self::apiKey() !== '';
    }
}
