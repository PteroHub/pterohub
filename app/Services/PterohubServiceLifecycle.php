<?php
namespace App\Services;

use App\Support\PterohubPterodactylConfig as ApiConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Read-only manual diagnostics and renewal estimates. No remote or billing writes. */
final class PterohubServiceLifecycle
{
    public function inspect(object $service): array
    {
        $result = ['result'=>'unavailable', 'installation'=>null, 'suspended'=>null];
        if (!$service->remote_server_id || !$service->remote_server_uuid || !$service->order_item_id) {
            return ['result'=>'unlinked', 'installation'=>null, 'suspended'=>null];
        }
        if (!ApiConfig::configured()) return $result;
        try {
            $response = Http::withToken(ApiConfig::apiKey())
                ->accept('application/vnd.pterodactyl.v1+json')
                ->timeout(12)->withOptions(['allow_redirects'=>false])
                ->get(ApiConfig::url().'/api/application/servers/'.(int)$service->remote_server_id);
            $status = $response->status();
            if ($status === 404) return array_replace($result, ['result'=>'remote_missing']);
            if ($status === 403 || $status === 401) return array_replace($result, ['result'=>'access_denied']);
            if ($status === 429) return array_replace($result, ['result'=>'rate_limited']);
            if (!$response->successful()) return array_replace($result, ['result'=>'api_unavailable']);
            $a = $response->json('attributes');
            if (!is_array($a)) return array_replace($result, ['result'=>'invalid_response']);
            $link = DB::table('pterohub_pterodactyl_user_links')->where('user_id', $service->user_id)->first();
            $node = $service->pterodactyl_node_id ? DB::table('pterodactyl_nodes')->where('id', $service->pterodactyl_node_id)->first() : null;
            if (!$link || !$node) return array_replace($result, ['result'=>'mapping_missing']);
            $valid = (int)($a['id'] ?? 0) === (int)$service->remote_server_id
                && hash_equals((string)$service->remote_server_uuid, (string)($a['uuid'] ?? ''))
                && ($a['external_id'] ?? null) === 'pterohub-order-item-'.$service->order_item_id
                && (int)($a['user'] ?? 0) === (int)$link->remote_user_id
                && (int)($a['node'] ?? 0) === (int)$node->remote_id;
            if (!$valid) return array_replace($result, ['result'=>'identity_mismatch']);
            $raw = $a['status'] ?? null;
            $installation = match ($raw) {
                'installing'=>'Installing', 'install_failed'=>'Installation failed',
                'reinstall_failed'=>'Reinstallation failed', 'restoring_backup'=>'Restoring backup',
                null, ''=>'Not reported', default=>'Other remote state',
            };
            return ['result'=>'verified', 'installation'=>$installation,
                'suspended'=>array_key_exists('suspended',$a) ? (bool)$a['suspended'] : null];
        } catch (\Throwable $e) {
            // No response body or credentials are stored. Network failures are not interpreted as missing servers.
            return $result;
        }
    }

    public function renewalPreview(object $service): array
    {
        $item = DB::table('order_items as oi')->join('orders as o','o.id','=','oi.order_id')
            ->where('oi.id',$service->order_item_id)->where('o.user_id',$service->user_id)
            ->select('oi.billing_months','oi.amount_minor','o.currency')->first();
        if (!$item || (int)$item->billing_months < 1 || (int)$item->billing_months > 36) {
            return ['available'=>false];
        }
        // Original order item amount is an indicative snapshot, not a live quote or tax calculation.
        $anchor = $service->next_due_at ?: ($service->provisioned_at ?: $service->created_at);
        $next = \Illuminate\Support\Carbon::parse($anchor)->addMonthsNoOverflow((int)$item->billing_months);
        return ['available'=>true, 'months'=>(int)$item->billing_months,
            'amount_minor'=>(int)$item->amount_minor, 'currency'=>$item->currency,
            'example_date'=>$next->toDateString(), 'has_due_date'=>(bool)$service->next_due_at];
    }
}
