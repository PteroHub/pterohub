<?php
namespace App\Http\Controllers;

use App\Services\PterohubClientServerSnapshot;
use App\Services\PterohubServiceLifecycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Read-only service administration. Never mutates remote servers or service lifecycle. */
final class PterohubServicesController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->check() && (bool) auth()->user()->is_pterohub_admin, 403);
    }

    private function baseQuery()
    {
        return DB::table('hosting_services as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->join('hosting_plans as p', 'p.id', '=', 's.hosting_plan_id')
            ->leftJoin('pterodactyl_nodes as n', 'n.id', '=', 's.pterodactyl_node_id')
            ->leftJoin('order_items as oi', 'oi.id', '=', 's.order_item_id')
            ->leftJoin('orders as o', 'o.id', '=', 'oi.order_id')
            ->select('s.*', 'u.name as customer_name', 'u.email as customer_email',
                'p.name as plan_name', 'n.name as node_name', 'n.remote_id as node_remote_id',
                'o.id as order_id', 'o.reference as order_reference');
    }

    public function index(Request $request)
    {
        $this->guard();
        $query = $this->baseQuery();
        $status = (string) $request->query('status', '');
        if ($status !== '' && in_array($status, ['provisioned','active','suspended','provisioning','provisioning_review','provisioning_retry_ready','terminated'], true)) {
            $query->where('s.status', $status);
        }
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($search, 0, 100));
            $query->where(function ($q) use ($search) {
                $q->where('u.email', 'like', '%'.$search.'%')
                    ->orWhere('p.name', 'like', '%'.$search.'%');
                if (ctype_digit($search)) $q->orWhere('s.id', (int) $search);
            });
        }
        $services = $query->orderByDesc('s.id')->paginate(20)->withQueryString();
        return view('pterohub.services.index', compact('services', 'search', 'status'));
    }

    public function show(int $id)
    {
        $this->guard();
        $service = $this->baseQuery()->where('s.id', $id)->first();
        abort_unless($service, 404);
        // Display a cached verified snapshot; never assume 'Provisioned' means online.
        $remote = app(PterohubClientServerSnapshot::class)->inspect($service, (int) $service->user_id);
        $checks = DB::table('pterohub_service_checks as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.actor_user_id')
            ->where('c.hosting_service_id', $id)
            ->select('c.*', 'u.name as actor_name')
            ->orderByDesc('c.id')->limit(30)->get();
        $events = DB::table('pterohub_service_events as e')
            ->leftJoin('users as au','au.id','=','e.actor_user_id')
            ->where('e.hosting_service_id',$id)->select('e.*','au.name as actor_name')
            ->orderByDesc('e.id')->limit(30)->get();
        $latestSync = $events->first(fn ($event) => $event->event_type === 'remote_sync');
        $renewal = app(PterohubServiceLifecycle::class)->renewalPreview($service);
        $renewalPlan = DB::table('pterohub_renewal_plans')->where('hosting_service_id',$id)->first();
        $renewalPlanEvents = DB::table('pterohub_renewal_plan_events')->where('hosting_service_id',$id)->orderByDesc('id')->limit(15)->get();
        $attempts = DB::table('provisioning_attempts')->where('hosting_service_id', $id)
            ->orderByDesc('id')->limit(15)->get();
        return view('pterohub.services.show', compact('service', 'remote', 'checks', 'attempts', 'events', 'latestSync', 'renewal', 'renewalPlan', 'renewalPlanEvents'));
    }

    public function verify(Request $request, int $id)
    {
        $this->guard();
        // POST only to our own application. Remote operation is a single GET.
        $service = $this->baseQuery()->where('s.id', $id)->first();
        abort_unless($service, 404);
        if ($service->remote_server_id && $service->remote_server_uuid && $service->pterodactyl_node_id) {
            $link = DB::table('pterohub_pterodactyl_user_links')->where('user_id', $service->user_id)->first();
            $node = DB::table('pterodactyl_nodes')->where('id', $service->pterodactyl_node_id)->first();
            if ($link && $node) {
                Cache::forget(PterohubClientServerSnapshot::cacheKey(
                    $service, (int) $service->user_id, (int) $link->remote_user_id, (int) $node->remote_id
                ));
            }
        }
        $remote = app(PterohubClientServerSnapshot::class)->inspect($service, (int) $service->user_id);
        $result = $remote['available'] ? 'verified' : 'unavailable_or_mismatch';
        DB::table('pterohub_service_checks')->insert([
            'hosting_service_id' => $id,
            'actor_user_id' => auth()->id(),
            'result' => $result,
            'remote_server_id' => $service->remote_server_id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect()->route('pterohub.services.show', $id)->with('ok',
            $remote['available'] ? 'Remote server identity verified (read-only).' :
            'Remote server could not be verified. Local service was not changed. Review Pterodactyl and application logs.');
    }
    /** Explicit admin-only remote GET; persists a minimal audit snapshot, never updates hosting_services. */
    public function sync(Request $request, int $id)
    {
        $this->guard();
        $service = $this->baseQuery()->where('s.id',$id)->first();
        abort_unless($service,404);
        $snapshot = app(PterohubServiceLifecycle::class)->inspect($service);
        DB::table('pterohub_service_events')->insert([
            'hosting_service_id'=>$id, 'actor_user_id'=>auth()->id(),
            'event_type'=>'remote_sync', 'result'=>$snapshot['result'],
            'remote_server_id'=>$service->remote_server_id,
            'installation'=>$snapshot['installation'], 'remote_suspended'=>$snapshot['suspended'],
            'created_at'=>now(),
        ]);
        // The client snapshot is verified independently and should not be overwritten by diagnostics.
        return redirect()->route('pterohub.services.show',$id)->with('ok',
            'Remote read-only check recorded: '.str_replace('_',' ',$snapshot['result']).'. Local service unchanged.');
    }

    /** Dry-run only: records preview event but does not create invoices, payments or due dates. */
    public function previewRenewal(Request $request, int $id)
    {
        $this->guard();
        $service = $this->baseQuery()->where('s.id',$id)->first();
        abort_unless($service,404);
        $preview = app(PterohubServiceLifecycle::class)->renewalPreview($service);
        DB::table('pterohub_service_events')->insert([
            'hosting_service_id'=>$id, 'actor_user_id'=>auth()->id(),
            'event_type'=>'renewal_preview', 'result'=>$preview['available'] ? 'simulated' : 'not_available',
            'remote_server_id'=>$service->remote_server_id, 'created_at'=>now(),
        ]);
        return redirect()->route('pterohub.services.show',$id)->with('ok',
            $preview['available'] ? 'Renewal estimate recorded. No invoice, payment or due date was created.' :
            'Renewal estimate unavailable for this order item. No changes were made.');
    }

}
