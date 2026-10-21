<?php
namespace App\Services;

use App\Support\PterohubPterodactylConfig as ApiConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Client-facing, read-only snapshot. Never sends server commands or changes local service state. */
final class PterohubClientServerSnapshot
{
    public static function cacheKey(object $service, int $customerId, int $remoteUserId, int $remoteNodeId): string
    {
        // A mapping change or UUID change must invalidate any previously verified snapshot.
        return 'ph:client:remote-snapshot:v2:'.hash('sha256', implode(':', [
            (int) $service->id, $customerId, (int) $service->remote_server_id,
            (string) $service->remote_server_uuid, $remoteUserId, $remoteNodeId,
        ]));
    }

    public function inspect(object $service, int $customerId): array
    {
        $fallback = ['available' => false, 'message' => 'Remote server details are temporarily unavailable.',
            'checked_at' => null, 'installation' => null, 'suspended' => null,
            'panel_url' => null, 'identifier' => null];
        // Defense in depth: never inspect a service on behalf of a different customer.
        if ((int) ($service->user_id ?? 0) !== $customerId) {
            return $fallback;
        }
        if (!$service->remote_server_id || !$service->remote_server_uuid || !ApiConfig::configured()) {
            $fallback['message'] = 'This service does not have a verified remote server connection yet.';
            return $fallback;
        }
        $link = DB::table('pterohub_pterodactyl_user_links')->where('user_id', $customerId)->first();
        $node = $service->pterodactyl_node_id
            ? DB::table('pterodactyl_nodes')->where('id', $service->pterodactyl_node_id)->first() : null;
        if (!$link || !$node) {
            $fallback['message'] = 'Remote account or node mapping is not available. Contact support.';
            return $fallback;
        }
        $cacheKey = self::cacheKey($service, $customerId, (int) $link->remote_user_id, (int) $node->remote_id);
        try {
            // Cache only verified results; a remote outage never becomes a misleading cached status.
            return Cache::remember($cacheKey, 60, function () use ($service, $customerId, $link, $node, $fallback) {
                $response = Http::withToken(ApiConfig::apiKey())
                    ->accept('application/vnd.pterodactyl.v1+json')
                    ->timeout(8)->withOptions(['allow_redirects' => false])
                    ->get(ApiConfig::url().'/api/application/servers/'.(int)$service->remote_server_id);
                if (!$response->successful()) {
                    Log::warning('PteroHub client server snapshot unavailable', ['service_id' => $service->id, 'http_status' => $response->status()]);
                    throw new \RuntimeException('Remote status unavailable.');
                }
                $attributes = $response->json('attributes');
                if (!is_array($attributes)) throw new \RuntimeException('Invalid remote status response.');
                // The server must belong to this order, linked Pterodactyl user, node and UUID.
                $valid = (int)($attributes['id'] ?? 0) === (int)$service->remote_server_id
                    && hash_equals((string)$service->remote_server_uuid, (string)($attributes['uuid'] ?? ''))
                    && ($attributes['external_id'] ?? null) === 'pterohub-order-item-'.$service->order_item_id
                    && (int)($attributes['user'] ?? 0) === (int)$link->remote_user_id
                    && (int)($attributes['node'] ?? 0) === (int)$node->remote_id;
                if (!$valid) {
                    Log::warning('PteroHub client remote server identity mismatch', ['service_id' => $service->id]);
                    throw new \RuntimeException('Remote server identity mismatch.');
                }
                $identifier = $attributes['identifier'] ?? null;
                $identifier = is_string($identifier) && preg_match('/^[a-zA-Z0-9]{6,20}$/D', $identifier) ? $identifier : null;
                $remoteStatus = $attributes['status'] ?? null;
                $installation = match ($remoteStatus) {
                    'installing' => 'Installing',
                    'install_failed' => 'Installation failed',
                    'reinstall_failed' => 'Reinstallation failed',
                    'restoring_backup' => 'Restoring backup',
                    null, '' => 'Not reported',
                    default => 'Other remote state',
                };
                return [
                    'available' => true, 'message' => null,
                    'checked_at' => now()->toIso8601String(),
                    'installation' => $installation,
                    'suspended' => array_key_exists('suspended', $attributes) ? (bool)$attributes['suspended'] : null,
                    'identifier' => $identifier,
                    'panel_url' => $identifier ? ApiConfig::url().'/server/'.$identifier : null,
                ];
            });
        } catch (\Throwable $e) {
            Log::warning('PteroHub client server snapshot request failed', ['service_id' => $service->id, 'exception' => get_class($e)]);
            return $fallback;
        }
    }
}
