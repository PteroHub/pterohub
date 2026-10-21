<?php

namespace App\Console\Commands;

use App\Services\PterodactylCatalogApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncPterodactylCatalog extends Command
{
    protected $signature = 'pterohub:sync-catalog {--dry-run : Fetch and show counts without writing}';
    protected $description = 'Read-only Pterodactyl API inventory sync (never enables sale or provisions servers)';

    public function handle(PterodactylCatalogApi $api): int
    {
        try {
            // Fetch both inventories fully BEFORE touching our local database.
            $eggs = $api->eggs();
            $nodes = $api->nodes();
            foreach (array_merge($eggs, $nodes) as $row) {
                if (!isset($row['id']) || !is_numeric($row['id'])) {
                    throw new \RuntimeException('Invalid API object: missing numeric id');
                }
            }
            $this->info('Eggs: '.count($eggs).' | Nodes: '.count($nodes));
            foreach ($eggs as $egg) {
                $this->line(sprintf('Egg #%d %s', (int) $egg['id'], (string) ($egg['name'] ?? '[unnamed]')));
            }
            if ($this->option('dry-run')) {
                $this->info('Dry-run: no database changes.');
                return self::SUCCESS;
            }
            DB::transaction(function () use ($eggs, $nodes) {
                $now = now();
                foreach ($eggs as $egg) {
                    $id = (int) $egg['id'];
                    $existing = DB::table('pterodactyl_eggs')->where('remote_id', $id)->exists();
                    $variables = $egg['relationships']['variables']['data'] ?? [];
                    DB::table('pterodactyl_eggs')->updateOrInsert(['remote_id' => $id], [
                        'name' => (string) ($egg['name'] ?? "Egg $id"),
                        'docker_image' => (string) ($egg['docker_image'] ?? $egg['docker_images']['Default'] ?? ''),
                        'startup' => (string) ($egg['startup'] ?? ''),
                        'environment_schema' => json_encode($variables, JSON_THROW_ON_ERROR),
                        'remote_payload' => json_encode($egg, JSON_THROW_ON_ERROR),
                        'last_synced_at' => $now,
                        'updated_at' => $now,
                    ] + ($existing ? [] : ['created_at' => $now, 'enabled_for_sale' => false]));
                }
                foreach ($nodes as $node) {
                    $id = (int) $node['id'];
                    $existing = DB::table('pterodactyl_nodes')->where('remote_id', $id)->exists();
                    DB::table('pterodactyl_nodes')->updateOrInsert(['remote_id' => $id], [
                        'name' => (string) ($node['name'] ?? "Node $id"),
                        'fqdn' => $node['fqdn'] ?? null,
                        'remote_location_id' => $node['location_id'] ?? null,
                        'remote_payload' => json_encode($node, JSON_THROW_ON_ERROR),
                        'last_synced_at' => $now,
                        'updated_at' => $now,
                    ] + ($existing ? [] : ['created_at' => $now, 'enabled_for_sale' => false]));
                }
            });
            $this->info('Catalog synced. Existing game associations and sale flags preserved.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
