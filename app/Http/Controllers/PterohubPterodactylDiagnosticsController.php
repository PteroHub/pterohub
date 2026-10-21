<?php

namespace App\Http\Controllers;

use App\Services\PterodactylReadOnlyDiagnostics;
use App\Support\PterohubPterodactylConfig as ApiConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PterohubPterodactylDiagnosticsController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->check() && (bool) auth()->user()->is_pterohub_admin, 403);
    }

    public function index()
    {
        $this->guard();
        $nodes = DB::table('pterodactyl_nodes')->orderBy('name')->get();
        $eggCount = DB::table('pterodactyl_eggs')->count();
        $linked = DB::table('hosting_plan_nodes')->count();
        $configured = ApiConfig::configured();
        $plans = DB::table('hosting_plans')->orderBy('name')->get(['id', 'name', 'memory_mb', 'disk_mb', 'cpu_percent', 'allocations', 'active']);
        $orderItems = DB::table('order_items as oi')->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('hosting_plans as p', 'p.id', '=', 'oi.hosting_plan_id')
            ->where('o.status', 'confirmed')->orderByDesc('oi.id')->limit(40)
            ->get(['oi.id', 'o.reference', 'p.name as plan_name']);
        return view('pterohub.infrastructure.diagnostics', compact('nodes', 'eggCount', 'linked', 'configured', 'plans', 'orderItems'));
    }

    public function testConnection(PterodactylReadOnlyDiagnostics $api)
    {
        $this->guard();
        try {
            return redirect()->route('pterohub.pterodactyl.index')
                ->with('ph_diagnostic', ['kind' => 'connection', 'result' => $api->connection()]);
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('pterohub.pterodactyl.index')
                ->with('ph_diagnostic', ['kind' => 'error', 'message' => $this->safeError($e)]);
        }
    }

    public function testNode(Request $request, PterodactylReadOnlyDiagnostics $api)
    {
        $this->guard();
        $data = $request->validate(['node_id' => ['required', 'integer', 'exists:pterodactyl_nodes,id']]);
        $node = DB::table('pterodactyl_nodes')->where('id', $data['node_id'])->first();
        abort_unless($node, 404);
        try {
            return redirect()->route('pterohub.pterodactyl.index')
                ->with('ph_diagnostic', [
                    'kind' => 'allocations', 'node' => $node->name,
                    'result' => $api->allocations((int) $node->remote_id),
                ]);
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('pterohub.pterodactyl.index')
                ->with('ph_diagnostic', [
                    'kind' => 'error', 'node' => $node->name,
                    'message' => $this->safeError($e),
                ]);
        }
    }

    public function checkCapacity(Request $request, PterodactylReadOnlyDiagnostics $api)
    {
        $this->guard();
        $data = $request->validate(['node_id' => ['required', 'integer', 'exists:pterodactyl_nodes,id']]);
        $node = DB::table('pterodactyl_nodes')->where('id', $data['node_id'])->first();
        abort_unless($node, 404);
        try {
            $result = $api->capacity((int) $node->remote_id);
            // Only PteroHub-known services, NOT all Pterodactyl servers.
            $services = DB::table('hosting_services as s')
                ->join('hosting_plans as p', 'p.id', '=', 's.hosting_plan_id')
                ->where('s.pterodactyl_node_id', $node->id)
                ->whereNotIn('s.status', ['terminated', 'cancelled'])
                ->selectRaw('COUNT(*) as service_count, COALESCE(SUM(p.memory_mb), 0) as reserved_memory_mb, COALESCE(SUM(p.disk_mb), 0) as reserved_disk_mb')
                ->first();
            $plans = DB::table('hosting_plan_nodes as hpn')
                ->join('hosting_plans as p', 'p.id', '=', 'hpn.hosting_plan_id')
                ->where('hpn.pterodactyl_node_id', $node->id)
                ->orderBy('p.name')
                ->get(['p.name', 'p.memory_mb', 'p.disk_mb', 'p.cpu_percent', 'p.allocations', 'p.active']);
            return redirect()->route('pterohub.pterodactyl.index')->with('ph_diagnostic', [
                'kind' => 'capacity', 'node' => $node->name, 'result' => $result,
                'services' => (array) $services, 'plans' => $plans->map(fn ($p) => (array) $p)->all(),
                'enabled_for_sale' => (bool) $node->enabled_for_sale,
            ]);
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('pterohub.pterodactyl.index')->with('ph_diagnostic', [
                'kind' => 'error', 'node' => $node->name, 'message' => $this->safeError($e),
            ]);
        }
    }

    /**
     * Read-only provisioning preflight. Never writes to Pterodactyl or the local DB.
     * All checks are advisory; there is no automatic placement or provisioning.
     */
    public function preflight(Request $request, PterodactylReadOnlyDiagnostics $api)
    {
        $this->guard();
        $data = $request->validate([
            'node_id' => ['required', 'integer', 'exists:pterodactyl_nodes,id'],
            'plan_id' => ['required', 'integer', 'exists:hosting_plans,id'],
        ]);
        $node = DB::table('pterodactyl_nodes')->where('id', $data['node_id'])->first();
        $plan = DB::table('hosting_plans')->where('id', $data['plan_id'])->first();
        abort_unless($node && $plan, 404);
        try {
            $capacity = $api->capacity((int) $node->remote_id);
            $allocations = $api->allocations((int) $node->remote_id);
            $game = DB::table('games')->where('id', $plan->game_id)->first();
            $egg = DB::table('pterodactyl_eggs')->where('id', $plan->pterodactyl_egg_id)->first();
            $mapping = DB::table('hosting_plan_nodes')
                ->where('hosting_plan_id', $plan->id)
                ->where('pterodactyl_node_id', $node->id)->exists();
            $price = DB::table('plan_prices')->where('hosting_plan_id', $plan->id)->exists();
            $checks = [
                ['label' => 'Product published', 'pass' => (bool) $plan->active, 'action' => 'Publish the product in Hosting Products.'],
                ['label' => 'Game enabled', 'pass' => $game && (bool) $game->active, 'action' => 'Enable the game in the catalog.'],
                ['label' => 'Egg enabled for sale', 'pass' => $egg && (bool) $egg->enabled_for_sale, 'action' => 'Enable the selected Egg for sale.'],
                ['label' => 'Price configured', 'pass' => $price, 'action' => 'Configure a billing period and price for this product.'],
                ['label' => 'Product assigned to node', 'pass' => $mapping, 'action' => 'Assign the product to this node in Locations & Nodes.'],
                ['label' => 'Node enabled for sale', 'pass' => (bool) $node->enabled_for_sale, 'action' => 'Explicitly enable the node for sale in Locations & Nodes.'],
                ['label' => 'Node not in maintenance', 'pass' => !$capacity['maintenance'], 'action' => 'Disable maintenance mode in Pterodactyl only when the node is ready.'],
                ['label' => 'RAM fits configured node maximum', 'pass' => (int) $plan->memory_mb > 0 && (int) $plan->memory_mb <= (int) $capacity['memory_mb'], 'action' => 'Adjust the product RAM or node configuration.'],
                ['label' => 'Disk fits configured node maximum', 'pass' => (int) $plan->disk_mb > 0 && (int) $plan->disk_mb <= (int) $capacity['disk_mb'], 'action' => 'Adjust the product disk or node configuration.'],
                ['label' => 'Requested ports fit currently unassigned allocations', 'pass' => (int) $plan->allocations > 0 && (int) $plan->allocations <= (int) $allocations['unassigned'], 'action' => 'Add free allocations or reduce requested ports.'],
                ['label' => 'CPU limit configured', 'pass' => (int) $plan->cpu_percent > 0, 'action' => 'Configure a positive CPU limit for the product.'],
            ];
            $blocking = count(array_filter($checks, fn ($check) => !$check['pass']));
            return redirect()->route('pterohub.pterodactyl.index')->with('ph_diagnostic', [
                'kind' => 'preflight', 'node' => $node->name, 'plan' => $plan->name,
                'checks' => $checks, 'blocking' => $blocking,
                'capacity' => $capacity, 'allocations' => $allocations,
                'note' => 'This checks configuration and current allocation counts only. Existing Pterodactyl server reservations, actual Wings health, CPU load, and concurrency are not verified. No server action was attempted.',
            ]);
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('pterohub.pterodactyl.index')->with('ph_diagnostic', [
                'kind' => 'error', 'node' => $node->name, 'message' => $this->safeError($e),
            ]);
        }
    }

    /** Read-only review of remote reservations, including servers created outside PteroHub. */
    public function checkReservations(Request $request, PterodactylReadOnlyDiagnostics $api)
    {
        $this->guard();
        $data = $request->validate(['node_id' => ['required', 'integer', 'exists:pterodactyl_nodes,id']]);
        $node = DB::table('pterodactyl_nodes')->where('id', $data['node_id'])->first();
        abort_unless($node, 404);
        try {
            $capacity = $api->capacity((int) $node->remote_id);
            $reservations = $api->serverReservations((int) $node->remote_id);
            $allocations = $api->allocations((int) $node->remote_id);
            $remainingRam = max(0, $capacity['memory_mb'] - $reservations['reserved_memory_mb']);
            $remainingDisk = max(0, $capacity['disk_mb'] - $reservations['reserved_disk_mb']);
            return redirect()->route('pterohub.pterodactyl.index')->with('ph_diagnostic', [
                'kind' => 'reservations', 'node' => $node->name, 'capacity' => $capacity,
                'reservations' => $reservations, 'allocations' => $allocations,
                'remaining_ram_mb' => $remainingRam, 'remaining_disk_mb' => $remainingDisk,
            ]);
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('pterohub.pterodactyl.index')->with('ph_diagnostic', [
                'kind' => 'error', 'node' => $node->name, 'message' => $this->safeError($e),
            ]);
        }
    }

    /**
     * Order-item safety gate only. This never creates services, attempts, users or servers.
     * A passing check does NOT authorize a real server creation.
     */
    public function checkOrderItem(Request $request, PterodactylReadOnlyDiagnostics $api)
    {
        $this->guard();
        $data = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'node_id' => ['required', 'integer', 'exists:pterodactyl_nodes,id'],
        ]);
        $item = DB::table('order_items as oi')->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('hosting_plans as p', 'p.id', '=', 'oi.hosting_plan_id')
            ->where('oi.id', $data['order_item_id'])
            ->select('oi.*', 'o.status as order_status', 'o.reference as order_reference',
                'p.name as plan_name', 'p.memory_mb', 'p.disk_mb', 'p.allocations', 'p.active')->first();
        $node = DB::table('pterodactyl_nodes')->where('id', $data['node_id'])->first();
        abort_unless($item && $node, 404);
        try {
            $capacity = $api->capacity((int) $node->remote_id);
            $reservations = $api->serverReservations((int) $node->remote_id);
            $allocations = $api->allocations((int) $node->remote_id);
            $service = DB::table('hosting_services')->where('order_item_id', $item->id)->first();
            $mapping = DB::table('hosting_plan_nodes')
                ->where('hosting_plan_id', $item->hosting_plan_id)
                ->where('pterodactyl_node_id', $node->id)->exists();
            $checks = [
                ['label' => 'Order confirmed', 'pass' => $item->order_status === 'confirmed'],
                ['label' => 'Product published', 'pass' => (bool) $item->active],
                ['label' => 'Product assigned to selected node', 'pass' => $mapping],
                ['label' => 'Node enabled for sale', 'pass' => (bool) $node->enabled_for_sale],
                ['label' => 'Node not in maintenance', 'pass' => !$capacity['maintenance']],
                ['label' => 'Order item not pinned to another node', 'pass' => !$item->pterodactyl_node_id || (int) $item->pterodactyl_node_id === (int) $node->id],
                ['label' => 'No service already linked to this order item', 'pass' => !$service],
                ['label' => 'RAM fits estimated unreserved configured limit', 'pass' => (int) $item->memory_mb > 0 && (int) $item->memory_mb <= max(0, $capacity['memory_mb'] - $reservations['reserved_memory_mb'])],
                ['label' => 'Disk fits estimated unreserved configured limit', 'pass' => (int) $item->disk_mb > 0 && (int) $item->disk_mb <= max(0, $capacity['disk_mb'] - $reservations['reserved_disk_mb'])],
                ['label' => 'Unassigned ports available', 'pass' => (int) $item->allocations > 0 && (int) $item->allocations <= $allocations['unassigned']],
            ];
            return redirect()->route('pterohub.pterodactyl.index')->with('ph_diagnostic', [
                'kind' => 'order-readiness', 'node' => $node->name, 'order' => $item->order_reference,
                'item_id' => $item->id, 'plan' => $item->plan_name, 'checks' => $checks,
                'blocking' => count(array_filter($checks, fn ($check) => !$check['pass'])),
                'reservations' => $reservations, 'capacity' => $capacity, 'allocations' => $allocations,
            ]);
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('pterohub.pterodactyl.index')->with('ph_diagnostic', [
                'kind' => 'error', 'node' => $node->name, 'message' => $this->safeError($e),
            ]);
        }
    }

    private function safeError(Throwable $e): string
    {
        $message = $e->getMessage();
        // Never echo raw network exception messages: they may include URLs or credentials.
        if (str_starts_with($message, 'Pterodactyl returned HTTP ')
            || str_starts_with($message, 'Configure HTTPS ')
            || str_starts_with($message, 'Pterodactyl returned an unexpected')
            || str_starts_with($message, 'Unexpected allocation')
            || str_starts_with($message, 'Allocation pagination')
            || str_starts_with($message, 'Pterodactyl API rate limit')
            || str_starts_with($message, 'Allocation check already in progress')
            || str_starts_with($message, 'Invalid node ID')
            || str_starts_with($message, 'Server inventory')
            || str_starts_with($message, 'Unexpected server')
            || str_starts_with($message, 'A server has unlimited')) {
            return $message;
        }
        return 'Could not reach the Pterodactyl Application API. Check HTTPS, DNS, network access and the API key. See Laravel logs for technical details.';
    }
}
