<?php
namespace App\Services;

use App\Support\PterohubPterodactylConfig as ApiConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;



/** Manual-only Pterodactyl Application API. Never retries POST requests. */
final class PterohubManualProvisioning
{
    private function api(string $method, string $path, array $payload = []): array
    {
        if (!ApiConfig::configured()) throw new RuntimeException('Configure the Pterodactyl HTTPS Application API first.');
        $request = Http::withToken(ApiConfig::apiKey())
            ->accept('application/vnd.pterodactyl.v1+json')->timeout(25)
            ->withOptions(['allow_redirects' => false]);
        $url = ApiConfig::url().'/api/application/'.$path;
        $response = $method === 'GET' ? $request->get($url, $payload) : $request->post($url, $payload);
        if (!$response->successful()) {
            // Never include remote response bodies: they may contain user-provided environment values.
            throw new PterohubRemoteHttpException($response->status(), $method);
        }
        $data = $response->json();
        if (!is_array($data)) throw new RuntimeException('Pterodactyl returned an invalid JSON response.');
        return $data;
    }

    public function remoteUser(int $remoteId): array
    {
        if ($remoteId < 1) throw new RuntimeException('Invalid remote user ID.');
        $data = $this->api('GET', 'users/'.$remoteId);
        $a = $data['attributes'] ?? data_get($data, 'data.attributes');
        if (!is_array($a) || (int) ($a['id'] ?? 0) !== $remoteId || empty($a['email'])) {
            throw new RuntimeException('Remote user response could not be verified.');
        }
        return $a;
    }

    public function egg(int $remoteId, ?int $nestId): array
    {
        if ($remoteId < 1) throw new RuntimeException('Invalid Egg ID.');
        try { $data = $this->api('GET', 'eggs/'.$remoteId, ['include' => 'variables']); }
        catch (RuntimeException $e) {
            if (!$nestId || !str_contains($e->getMessage(), 'HTTP 404')) throw $e;
            $data = $this->api('GET', 'nests/'.$nestId.'/eggs/'.$remoteId, ['include' => 'variables']);
        }
        $a = $data['attributes'] ?? data_get($data, 'data.attributes');
        if (!is_array($a) || (int) ($a['id'] ?? 0) !== $remoteId) throw new RuntimeException('Egg response could not be verified.');
        $variables = data_get($a, 'relationships.variables.data', data_get($data, 'relationships.variables.data', []));
        if (!is_array($variables)) throw new RuntimeException('Egg variables were not returned.');
        $result = [];
        foreach ($variables as $row) {
            $v = $row['attributes'] ?? null;
            if (!is_array($v) || !is_string($v['env_variable'] ?? null)) throw new RuntimeException('Invalid Egg variable response.');
            $name = $v['env_variable'];
            if (!preg_match('/^[A-Z][A-Z0-9_]{0,100}$/', $name)) throw new RuntimeException('Unsupported Egg variable name.');
            $result[$name] = [
                'name' => (string) ($v['name'] ?? $name),
                'default' => (string) ($v['default_value'] ?? ''),
                'required' => str_contains((string) ($v['rules'] ?? ''), 'required'),
                'rules' => (string) ($v['rules'] ?? ''),
            ];
        }
        return ['attributes' => $a, 'variables' => $result];
    }

    /** Fresh, uncached allocation list; fail closed on pagination anomalies. */
    public function freeAllocations(int $remoteNodeId): array
    {
        $free = []; $last = null;
        for ($page = 1; $page <= 100; $page++) {
            if ($page > 1) usleep(400000);
            $data = $this->api('GET', 'nodes/'.$remoteNodeId.'/allocations', ['page'=>$page,'per_page'=>100]);
            $pages = data_get($data, 'meta.pagination.total_pages');
            if (!is_numeric($pages) || (int) $pages < 1 || (int) $pages > 100 || ($last !== null && $last !== (int) $pages)) {
                throw new RuntimeException('Allocation pagination could not be verified.');
            }
            $last = (int) $pages;
            foreach ($data['data'] ?? [] as $row) {
                $a = $row['attributes'] ?? null;
                if (!is_array($a) || !is_numeric($a['id'] ?? null) || !array_key_exists('assigned', $a)) throw new RuntimeException('Invalid allocation response.');
                if (!$a['assigned']) $free[] = (int) $a['id'];
            }
            if ($page === $last) return $free;
        }
        throw new RuntimeException('Allocation pagination safety limit exceeded.');
    }

    /** Remote external ID is the stable idempotency marker for manual reconciliation. */
    public function findRemoteByExternalId(string $externalId): ?array
    {
        $last = null;
        for ($page=1; $page<=100; $page++) {
            if ($page>1) usleep(400000);
            $data = $this->api('GET', 'servers', ['page'=>$page,'per_page'=>100]);
            $pages = data_get($data, 'meta.pagination.total_pages');
            if (!is_numeric($pages) || (int)$pages<1 || (int)$pages>100 || ($last !== null && $last !== (int)$pages)) throw new RuntimeException('Server inventory could not be verified.');
            $last = (int)$pages;
            foreach ($data['data'] ?? [] as $row) {
                $a = $row['attributes'] ?? null;
                if (!is_array($a)) throw new RuntimeException('Invalid server inventory.');
                if (($a['external_id'] ?? null) === $externalId) return $a;
            }
            if ($page===$last) return null;
        }
        throw new RuntimeException('Server inventory safety limit exceeded.');
    }

    public function context(int $itemId, int $nodeId): array
    {
        $item = DB::table('order_items as oi')->join('orders as o','o.id','=','oi.order_id')
            ->join('users as u','u.id','=','o.user_id')->join('hosting_plans as p','p.id','=','oi.hosting_plan_id')
            ->join('pterodactyl_eggs as e','e.id','=','p.pterodactyl_egg_id')
            ->join('games as g','g.id','=','p.game_id')
            ->where('oi.id',$itemId)
            ->select('oi.*','o.status as order_status','o.reference as order_reference','o.user_id','u.email as customer_email',
                'p.name as plan_name','p.active as plan_active','p.memory_mb','p.disk_mb','p.cpu_percent','p.swap_mb','p.allocations','p.databases','p.backups',
                'p.pterodactyl_egg_id','p.startup_overrides','e.remote_id as remote_egg_id','e.remote_payload as egg_payload','e.enabled_for_sale as egg_enabled',
                'g.active as game_enabled')->first();
        $node = DB::table('pterodactyl_nodes')->where('id',$nodeId)->first();
        if (!$item || !$node) throw ValidationException::withMessages(['order_item_id'=>'Order item or node not found.']);
        $link = DB::table('pterohub_pterodactyl_user_links')->where('user_id',$item->user_id)->first();
        return compact('item','node','link');
    }

    public function blockers(object $item, object $node, ?object $link): array
    {
        $errors = [];
        if ($item->order_status !== 'confirmed') $errors[]='Order is not confirmed.';
        if (!(bool)$item->plan_active || !(bool)$item->egg_enabled || !(bool)$item->game_enabled) $errors[]='Product, Egg or game is not enabled.';
        if (!(bool)$node->enabled_for_sale) $errors[]='Node is not enabled for sale.';
        if (!DB::table('hosting_plan_nodes')->where('hosting_plan_id',$item->hosting_plan_id)->where('pterodactyl_node_id',$node->id)->exists()) $errors[]='Product is not assigned to this node.';
        if ($item->pterodactyl_node_id && (int)$item->pterodactyl_node_id !== (int)$node->id) $errors[]='Order item is pinned to another node.';
        if (!$link) $errors[]='Link the existing Pterodactyl user first.';
        $service = DB::table('hosting_services')->where('order_item_id', $item->id)->first();
        if ($service && ($service->status !== 'provisioning_retry_ready' || $service->remote_server_id !== null || (int)$service->pterodactyl_node_id !== (int)$node->id)) {
            $errors[]='A service record already exists for this item. Reconcile the previous attempt first.';
        }
        if ((int)$item->memory_mb<1 || (int)$item->disk_mb<1 || (int)$item->cpu_percent<1 || (int)$item->allocations<1) $errors[]='Invalid product resources.';
        return $errors;
    }

    public function createServer(array $payload): array
    {
        // Exactly one POST. Do not retry on network failures or 5xx responses.
        $data = $this->api('POST', 'servers', $payload);
        $a = $data['attributes'] ?? data_get($data, 'data.attributes');
        if (!is_array($a) || !is_numeric($a['id'] ?? null) || empty($a['uuid'])) throw new RuntimeException('Pterodactyl returned an unexpected server creation response.');
        return $a;
    }
}
