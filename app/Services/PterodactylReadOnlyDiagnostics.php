<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Support\PterohubPterodactylConfig as ApiConfig;
use RuntimeException;

/** Explicit, read-only Application API diagnostics. Never sends mutation requests. */
final class PterodactylReadOnlyDiagnostics
{
    private function get(string $path, array $query = [], bool $nodeDetail = false): array
    {
        $base = ApiConfig::url();
        $key = ApiConfig::apiKey();
        if (!preg_match('~^https://[^/]+~i', $base) || $key === '') {
            throw new RuntimeException('Configure the HTTPS panel URL and Application API key in Settings > Pterodactyl.');
        }
        $until = (int) Cache::get('ph:pterodactyl:api-cooldown', 0);
        if ($until > time()) {
            throw new RuntimeException('Pterodactyl API rate limit (HTTP 429). Wait '.($until - time()).' seconds before trying again.');
        }
        $response = Http::withToken($key)
            ->accept('application/vnd.pterodactyl.v1+json')
            ->timeout(12)
            ->withOptions(['allow_redirects' => false])
            ->get($base.'/api/application/'.$path, $query);
        if ($response->status() === 429) {
            // Honor the server's cooldown without issuing an automatic retry.
            $header = trim((string) $response->header('Retry-After', ''));
            $seconds = 60;
            if (ctype_digit($header)) {
                $seconds = max(1, min(300, (int) $header));
            } elseif ($header !== '' && ($when = strtotime($header)) !== false) {
                $seconds = max(1, min(300, $when - time()));
            }
            Cache::put('ph:pterodactyl:api-cooldown', time() + $seconds, $seconds);
            throw new RuntimeException('Pterodactyl API rate limit (HTTP 429). Wait '.$seconds.' seconds before trying again.');
        }
        if (!$response->successful()) {
            throw new RuntimeException('Pterodactyl returned HTTP '.$response->status().' for the requested read-only endpoint.');
        }
        $data = $response->json();
        if (!is_array($data)) {
            throw new RuntimeException('Pterodactyl returned an unexpected response format.');
        }
        if ($nodeDetail) {
            // Pterodactyl's single-node endpoint returns {object: node, attributes: {...}}
            // rather than the collection envelope {data: [...], meta: {...}}.
            // Accept an optional data wrapper for compatibility with API variants.
            $attributes = $data['attributes'] ?? data_get($data, 'data.attributes');
            if (!is_array($attributes)) {
                throw new RuntimeException('Pterodactyl returned an unexpected node response format.');
            }
            return ['attributes' => $attributes];
        }
        if (!isset($data['data']) || !is_array($data['data']) || !array_is_list($data['data'])) {
            throw new RuntimeException('Pterodactyl returned an unexpected response format.');
        }
        return $data;
    }

    public function connection(): array
    {
        $data = $this->get('nodes', ['page' => 1, 'per_page' => 1]);
        return [
            'status' => 'Connected',
            'nodes_total' => data_get($data, 'meta.pagination.total'),
            'message' => 'Application API authenticated successfully. Read-only node list available.',
        ];
    }

    /**
     * Read only node specification. This is NOT a live Wings usage measurement.
     * Never turn these estimates into automatic placement decisions.
     */
    public function capacity(int $remoteNodeId): array
    {
        if ($remoteNodeId < 1) throw new RuntimeException('Invalid node ID.');
        $cacheKey = 'ph:pterodactyl:capacity:'.hash('sha256', ApiConfig::url().'|'.$remoteNodeId);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) return $cached;

        $payload = $this->get('nodes/'.$remoteNodeId, [], true);
        $a = $payload['attributes'];
        if (!is_array($a)) throw new RuntimeException('Pterodactyl returned an unexpected node response format.');
        $memory = filter_var($a['memory'] ?? null, FILTER_VALIDATE_INT);
        $disk = filter_var($a['disk'] ?? null, FILTER_VALIDATE_INT);
        if ($memory === false || $disk === false || $memory < 1 || $disk < 1) {
            throw new RuntimeException('Pterodactyl returned an unexpected node resource format.');
        }
        $memoryOver = filter_var($a['memory_overallocate'] ?? 0, FILTER_VALIDATE_INT);
        $diskOver = filter_var($a['disk_overallocate'] ?? 0, FILTER_VALIDATE_INT);
        $result = [
            'node' => (string) ($a['name'] ?? 'Node'),
            'memory_mb' => $memory,
            'disk_mb' => $disk,
            'memory_overallocate' => $memoryOver === false ? null : $memoryOver,
            'disk_overallocate' => $diskOver === false ? null : $diskOver,
            'maintenance' => (bool) ($a['maintenance_mode'] ?? false),
            'public' => (bool) ($a['public'] ?? false),
            'status' => 'Configuration read',
            'message' => 'Configured node limits only. Existing Pterodactyl servers, real resource usage and Wings health are NOT verified.',
        ];
        Cache::put($cacheKey, $result, 60);
        return $result;
    }

    /**
     * Read-only Application API inventory of ALL Pterodactyl server reservations on a node.
     * This reports configured limits, not real-time Wings usage or physical free capacity.
     * Fails closed on malformed/incomplete pages instead of understating reservations.
     */
    public function serverReservations(int $remoteNodeId): array
    {
        if ($remoteNodeId < 1) throw new RuntimeException('Invalid node ID.');
        $cacheKey = 'ph:pterodactyl:servers:'.hash('sha256', ApiConfig::url().'|'.$remoteNodeId);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) return $cached;
        $lockKey = $cacheKey.':busy';
        if (!Cache::add($lockKey, 1, 90)) {
            throw new RuntimeException('Server inventory already in progress. Wait a moment before trying again.');
        }
        try {
            $serverCount = 0;
            $memory = 0;
            $disk = 0;
            $totalPages = null;
            $pages = 0;
            for ($page = 1; $page <= 100; $page++) {
                if ($page > 1) usleep(400000);
                $payload = $this->get('servers', ['page' => $page, 'per_page' => 100]);
                $last = data_get($payload, 'meta.pagination.total_pages');
                if (!is_numeric($last) || (int) $last < 1 || (int) $last > 100) {
                    throw new RuntimeException('Server inventory pagination is missing or exceeds the safety limit.');
                }
                if ($totalPages !== null && $totalPages !== (int) $last) {
                    throw new RuntimeException('Server inventory changed during pagination. Retry the check.');
                }
                $totalPages = (int) $last;
                foreach ($payload['data'] as $item) {
                    $a = $item['attributes'] ?? null;
                    if (!is_array($a) || !isset($a['node']) || !is_numeric($a['node'])) {
                        throw new RuntimeException('Unexpected server inventory entry format.');
                    }
                    if ((int) $a['node'] !== $remoteNodeId) continue;
                    $limits = $a['limits'] ?? null;
                    if (!is_array($limits)) throw new RuntimeException('Server resource limits missing from inventory.');
                    $m = filter_var($limits['memory'] ?? null, FILTER_VALIDATE_INT);
                    $d = filter_var($limits['disk'] ?? null, FILTER_VALIDATE_INT);
                    if ($m === false || $d === false || $m < 0 || $d < 0) {
                        throw new RuntimeException('Unexpected server resource limits in inventory.');
                    }
                    // Zero can mean unlimited in Pterodactyl. Do not infer free capacity.
                    if ($m === 0 || $d === 0) {
                        throw new RuntimeException('A server has unlimited RAM or disk; capacity cannot be estimated safely.');
                    }
                    $serverCount++;
                    $memory += $m;
                    $disk += $d;
                }
                $pages++;
                if ($page >= $totalPages) {
                    $result = [
                        'servers' => $serverCount, 'reserved_memory_mb' => $memory,
                        'reserved_disk_mb' => $disk, 'pages' => $pages,
                        'checked_at' => now()->toDateTimeString(),
                        'message' => 'Configured reservations across all Pterodactyl servers on this node. Not live Wings utilization.',
                    ];
                    Cache::put($cacheKey, $result, 45);
                    return $result;
                }
            }
            throw new RuntimeException('Server inventory pagination exceeded the safety limit.');
        } finally {
            Cache::forget($lockKey);
        }
    }

    public function allocations(int $remoteNodeId): array
    {
        if ($remoteNodeId < 1) throw new RuntimeException('Invalid node ID.');
        $cacheKey = 'ph:pterodactyl:allocations:'.hash('sha256', ApiConfig::url().'|'.$remoteNodeId);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $cached['message'] = 'Cached allocation counts (up to 60 seconds old). These do not confirm node capacity or availability.';
            return $cached;
        }

        // Prevent concurrent button presses from producing overlapping API scans.
        $lockKey = $cacheKey.':busy';
        if (!Cache::add($lockKey, 1, 90)) {
            throw new RuntimeException('Allocation check already in progress. Wait a moment before trying again.');
        }
        try {
            $total = 0;
            $assigned = 0;
            $pages = 0;
            for ($page = 1; $page <= 100; $page++) {
                if ($page > 1) usleep(400000); // Respect API budgets between pages.
                $response = $this->get('nodes/'.$remoteNodeId.'/allocations', ['page' => $page, 'per_page' => 100]);
                foreach ($response['data'] as $item) {
                    $attrs = $item['attributes'] ?? null;
                    if (!is_array($attrs)) throw new RuntimeException('Unexpected allocation entry format.');
                    $total++;
                    if (!empty($attrs['assigned'])) $assigned++;
                }
                $pages++;
                $last = (int) data_get($response, 'meta.pagination.total_pages', 1);
                if ($page >= $last) {
                    $result = [
                        'status' => 'Checked',
                        'total' => $total,
                        'assigned' => $assigned,
                        'unassigned' => $total - $assigned,
                        'pages' => $pages,
                        'message' => 'Allocation counts are informational only. They do not confirm node capacity or availability.',
                    ];
                    Cache::put($cacheKey, $result, 60);
                    return $result;
                }
            }
            throw new RuntimeException('Allocation pagination exceeded the safety limit.');
        } finally {
            Cache::forget($lockKey);
        }
    }
}
