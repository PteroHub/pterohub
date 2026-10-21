<?php
namespace App\Console\Commands;
use App\Services\PterodactylInventoryApi;
use Illuminate\Console\Command;
use Throwable;
final class PterohubInventoryCheck extends Command {
    protected $signature = 'pterohub:inventory-check';
    protected $description = 'Read-only Pterodactyl inventory probe; no writes or provisioning';
    public function handle(PterodactylInventoryApi $api): int {
        foreach (['nodes','servers','allocations'] as $type) {
            try {
                $rows=$api->pages($type);
                $this->info(ucfirst($type).': '.count($rows).' records');
            } catch (Throwable $e) {
                $this->warn(ucfirst($type).': unverified (endpoint unavailable or incompatible)');
            }
        }
        $this->warn('Inventory counts do NOT verify capacity. No availability or sales enabled.');
        return self::SUCCESS;
    }
}
