<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PterohubSystemSettings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!Schema::hasTable('pterohub_system_settings')) return $default;
        $row = DB::table('pterohub_system_settings')->where('key', $key)->first();
        if (!$row) return $default;
        return $row->is_secret ? Crypt::decryptString($row->value) : $row->value;
    }

    public static function configured(string $key): bool
    {
        if (!Schema::hasTable('pterohub_system_settings')) return false;
        return DB::table('pterohub_system_settings')->where('key', $key)->whereNotNull('value')->exists();
    }

    public static function put(string $key, ?string $value, bool $secret): void
    {
        DB::table('pterohub_system_settings')->updateOrInsert(['key' => $key], [
            'value' => $secret && $value !== null ? Crypt::encryptString($value) : $value,
            'is_secret' => $secret,
            'updated_at' => now(),
        ]);
    }
}
