<?php
namespace App\Http\Controllers;

use App\Services\PterohubManualProvisioning as Provision;
use App\Services\PterohubRemoteHttpException;
use App\Services\PterodactylReadOnlyDiagnostics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PterohubManualProvisioningController extends Controller
{
    private function guard(): void { abort_unless(auth()->check() && (bool) auth()->user()->is_pterohub_admin, 403); }
    private function fail(string $message): never { throw ValidationException::withMessages(['provisioning'=>$message]); }

    public function index()
    {
        $this->guard();
        $items = DB::table('order_items as oi')->join('orders as o','o.id','=','oi.order_id')
            ->join('hosting_plans as p','p.id','=','oi.hosting_plan_id')->join('users as u','u.id','=','o.user_id')
            ->where('o.status','confirmed')->orderByDesc('oi.id')->limit(100)
            ->get(['oi.id','o.reference','p.name as plan_name','u.email as customer_email']);
        $nodes = DB::table('pterodactyl_nodes')->orderBy('name')->get();
        $users = DB::table('users')->where('is_pterohub_admin',false)->orderBy('email')->get(['id','email','name']);
        $links = DB::table('pterohub_pterodactyl_user_links as l')->join('users as u','u.id','=','l.user_id')
            ->select('l.*','u.email as customer_email')->orderBy('u.email')->get();
        $attempts = DB::table('provisioning_attempts as a')->join('hosting_services as s','s.id','=','a.hosting_service_id')
            ->orderByDesc('a.id')->limit(15)->get(['a.id','a.action','a.status','a.created_at','a.finished_at','s.order_item_id','s.remote_server_id','s.status as service_status']);
        $reconcileServices=DB::table('hosting_services as s')
            ->join('order_items as oi','oi.id','=','s.order_item_id')
            ->join('orders as o','o.id','=','oi.order_id')
            ->whereIn('s.status',['provisioning_review','provisioning_retry_ready'])
            ->whereNull('s.remote_server_id')->orderByDesc('s.id')->limit(30)
            ->get(['s.id','s.order_item_id','s.status','o.reference']);
        return view('pterohub.infrastructure.manual-provisioning', compact('items','nodes','users','links','attempts','reconcileServices'));
    }

    /** Explicit, read-only remote check before authorizing a retry or linking a found server. */
    public function reconcile(Request $request, Provision $api)
    {
        $this->guard();
        $data=$request->validate([
            'service_id'=>'required|integer|exists:hosting_services,id',
            'confirmation'=>'required|string|max:80',
            'acknowledge'=>'accepted',
        ]);
        $serviceId=(int)$data['service_id'];
        if ($data['confirmation'] !== 'RECONCILE '.$serviceId) $this->fail('Type RECONCILE '.$serviceId.' exactly.');
        $service=DB::table('hosting_services')->where('id',$serviceId)->first();
        if (!$service || $service->remote_server_id !== null || !in_array($service->status,['provisioning_review','provisioning_retry_ready'],true)) {
            $this->fail('This service is not eligible for reconciliation.');
        }
        $attempt=DB::table('provisioning_attempts')->where('hosting_service_id',$serviceId)->orderByDesc('id')->first();
        if (!$attempt || !in_array($attempt->status,['uncertain','rejected','reconciled_absent'],true)) {
            $this->fail('No completed failed attempt can be reconciled. Pending attempts must not be retried.');
        }
        $ctx=$api->context((int)$service->order_item_id,(int)$service->pterodactyl_node_id);
        $item=$ctx['item'];$node=$ctx['node'];$link=$ctx['link'];
        $externalId='pterohub-order-item-'.$item->id;
        // A complete fresh inventory read is required; on any API failure no DB changes occur.
        $remote=$api->findRemoteByExternalId($externalId);
        if ($remote !== null) {
            // Never associate an unrelated remote server to this customer or node.
            if (!$link || (int)($remote['user'] ?? 0)!==(int)$link->remote_user_id
                || (int)($remote['egg'] ?? 0)!==(int)$item->remote_egg_id
                || (int)($remote['node'] ?? 0)!==(int)$node->remote_id
                || !is_numeric($remote['id'] ?? null) || empty($remote['uuid'])) {
                $this->fail('A remote server exists but its owner, Egg or node cannot be verified. Manual investigation required; nothing was changed.');
            }
        }
        DB::transaction(function () use ($serviceId,$service,$attempt,$remote) {
            $locked=DB::table('hosting_services')->where('id',$serviceId)->lockForUpdate()->first();
            $latest=DB::table('provisioning_attempts')->where('hosting_service_id',$serviceId)->orderByDesc('id')->lockForUpdate()->first();
            if (!$locked || $locked->remote_server_id!==null || !in_array($locked->status,['provisioning_review','provisioning_retry_ready'],true)
                || !$latest || (int)$latest->id!==(int)$attempt->id || !in_array($latest->status,['uncertain','rejected','reconciled_absent'],true)) {
                $this->fail('Provisioning state changed during reconciliation. No action taken.');
            }
            $now=now();
            if ($remote !== null) {
                DB::table('hosting_services')->where('id',$serviceId)->update([
                    'remote_server_id'=>(int)$remote['id'],'remote_server_uuid'=>(string)$remote['uuid'],
                    'status'=>'provisioned','provisioned_at'=>$now,'last_provision_error'=>null,'updated_at'=>$now,
                ]);
                DB::table('provisioning_attempts')->where('id',$latest->id)->update([
                    'status'=>'reconciled_found','error'=>'Remote server verified by external ID and linked without a new POST.',
                    'finished_at'=>$now,'updated_at'=>$now,
                ]);
            } else {
                DB::table('hosting_services')->where('id',$serviceId)->update([
                    'status'=>'provisioning_retry_ready','last_provision_error'=>null,'updated_at'=>$now,
                ]);
                DB::table('provisioning_attempts')->where('id',$latest->id)->update([
                    'status'=>'reconciled_absent','error'=>'Admin verified remote absence via complete Application API inventory.',
                    'finished_at'=>$now,'updated_at'=>$now,
                ]);
            }
        },3);
        if ($remote !== null) app(\App\Services\PterohubNotifications::class)->event('service_provisioned',(int)DB::table('hosting_services')->where('id',$serviceId)->value('user_id'),'#'.$serviceId,'service_provisioned:'.$serviceId);
        return redirect()->route('pterohub.manual.index')->with('ok',$remote
            ? 'Remote server verified and linked. No server was created.'
            : 'Remote server not found. Existing service is cleared for ONE new manual attempt after full preflight and explicit confirmation.');
    }

    public function link(Request $request, Provision $api)
    {
        $this->guard();
        $data=$request->validate(['user_id'=>'required|integer|exists:users,id','remote_user_id'=>'required|integer|min:1']);
        $customer=DB::table('users')->where('id',$data['user_id'])->first();
        if (!$customer || $customer->is_pterohub_admin) $this->fail('Choose a non-administrator customer.');
        $remote=$api->remoteUser((int)$data['remote_user_id']);
        if (strcasecmp(trim($customer->email),trim($remote['email']))!==0) $this->fail('Email mismatch: the existing Pterodactyl account must use the same email as the PteroHub customer.');
        DB::transaction(function () use ($data,$remote) {
            $existing=DB::table('pterohub_pterodactyl_user_links')->where('user_id',$data['user_id'])->lockForUpdate()->first();
            if ($existing && (int)$existing->remote_user_id !== (int)$data['remote_user_id']) $this->fail('This customer is already linked. Changing mappings is disabled in this Canary.');
            $other=DB::table('pterohub_pterodactyl_user_links')->where('remote_user_id',$data['remote_user_id'])->first();
            if ($other && (int)$other->user_id !== (int)$data['user_id']) $this->fail('This Pterodactyl account is already linked to another customer.');
            if (!$existing) DB::table('pterohub_pterodactyl_user_links')->insert([
                'user_id'=>$data['user_id'],'remote_user_id'=>$data['remote_user_id'],
                'remote_email'=>$remote['email'],'linked_by'=>auth()->id(), 'created_at'=>now(),'updated_at'=>now(),
            ]);
        },3);
        return redirect()->route('pterohub.manual.index')->with('ok','Existing Pterodactyl user verified and linked. No account or server was created.');
    }

    public function review(Request $request, Provision $api, PterodactylReadOnlyDiagnostics $diagnostics)
    {
        $this->guard();
        $data=$request->validate(['order_item_id'=>'required|integer|exists:order_items,id','node_id'=>'required|integer|exists:pterodactyl_nodes,id']);
        $ctx=$api->context((int)$data['order_item_id'],(int)$data['node_id']);
        ['item'=>$item,'node'=>$node,'link'=>$link]=$ctx;
        $blockers=$api->blockers($item,$node,$link);
        $egg=null;$free=[];$capacity=null;$reservations=null;
        try {
            if ($link) {
                $remote=$api->remoteUser((int)$link->remote_user_id);
                if (strcasecmp($remote['email'],$item->customer_email)!==0) $blockers[]='Pterodactyl user email no longer matches the customer.';
            }
            $eggPayload=json_decode((string)$item->egg_payload,true) ?: [];
            $nestId=(int)($eggPayload['nest'] ?? $eggPayload['nest_id'] ?? 0);
            $egg=$api->egg((int)$item->remote_egg_id,$nestId ?: null);
            $capacity=$diagnostics->capacity((int)$node->remote_id);
            $reservations=$diagnostics->serverReservations((int)$node->remote_id);
            $free=$api->freeAllocations((int)$node->remote_id);
            if ($capacity['maintenance']) $blockers[]='Node is in maintenance mode.';
            if ((int)$item->memory_mb > max(0,$capacity['memory_mb']-$reservations['reserved_memory_mb'])) $blockers[]='Insufficient estimated unreserved RAM.';
            if ((int)$item->disk_mb > max(0,$capacity['disk_mb']-$reservations['reserved_disk_mb'])) $blockers[]='Insufficient estimated unreserved disk.';
            if (count($free) < (int)$item->allocations) $blockers[]='Not enough unassigned allocations.';
            if ($api->findRemoteByExternalId('pterohub-order-item-'.$item->id)) $blockers[]='A remote server already has this order item external ID.';
            if (empty($egg['attributes']['startup']) || empty($egg['attributes']['docker_image'])) $blockers[]='Egg startup or Docker image is missing.';
        } catch (Throwable $e) { report($e); $blockers[]='Pterodactyl validation failed. Check Laravel logs; no action was performed.'; }
        return view('pterohub.infrastructure.manual-review',compact('item','node','link','egg','free','capacity','reservations','blockers'));
    }

    public function create(Request $request, Provision $api, PterodactylReadOnlyDiagnostics $diagnostics)
    {
        $this->guard();
        $data=$request->validate([
            'order_item_id'=>'required|integer|exists:order_items,id',
            'node_id'=>'required|integer|exists:pterodactyl_nodes,id',
            'confirmation'=>'required|string|max:80',
            'acknowledge'=>'accepted',
            'env'=>'nullable|array',
            'env.*'=>'nullable|string|max:4000',
        ]);
        $itemId=(int)$data['order_item_id'];$nodeId=(int)$data['node_id'];
        if ($data['confirmation'] !== 'PROVISION '.$itemId) $this->fail('Type PROVISION '.$itemId.' exactly to authorize one server creation.');
        $ctx=$api->context($itemId,$nodeId);
        ['item'=>$item,'node'=>$node,'link'=>$link]=$ctx;
        $blockers=$api->blockers($item,$node,$link);
        if ($blockers) $this->fail(implode(' ',$blockers));
        $remote=$api->remoteUser((int)$link->remote_user_id);
        if (strcasecmp($remote['email'],$item->customer_email)!==0) $this->fail('Pterodactyl account email mismatch.');
        $eggPayload=json_decode((string)$item->egg_payload,true) ?: [];
        $nestId=(int)($eggPayload['nest'] ?? $eggPayload['nest_id'] ?? 0);
        $egg=$api->egg((int)$item->remote_egg_id,$nestId ?: null);
        $environment=[];
        $submitted=$data['env'] ?? [];
        foreach ($submitted as $name=>$value) if (!array_key_exists($name,$egg['variables'])) $this->fail('Unknown Egg environment variable.');
        foreach ($egg['variables'] as $name=>$variable) {
            $value=(string)($submitted[$name] ?? $variable['default']);
            if ($variable['required'] && trim($value)==='') $this->fail('A required Egg variable is empty: '.$name);
            $environment[$name]=$value;
        }
        $capacity=$diagnostics->capacity((int)$node->remote_id);
        $reservations=$diagnostics->serverReservations((int)$node->remote_id);
        $free=$api->freeAllocations((int)$node->remote_id);
        if ($capacity['maintenance'] || (int)$item->memory_mb > max(0,$capacity['memory_mb']-$reservations['reserved_memory_mb'])
            || (int)$item->disk_mb > max(0,$capacity['disk_mb']-$reservations['reserved_disk_mb'])
            || count($free)<(int)$item->allocations) $this->fail('Capacity or allocation checks no longer pass.');
        $externalId='pterohub-order-item-'.$itemId;
        if ($api->findRemoteByExternalId($externalId)) $this->fail('Remote server with this external ID already exists. No new server was created.');
        $allocationIds=array_slice($free,0,(int)$item->allocations);
        // A durable local reservation is committed BEFORE the remote POST.
        // If the POST outcome is uncertain, never retry automatically.
        [$serviceId,$attemptId]=DB::transaction(function () use ($item,$node,$link) {
            $locked=DB::table('order_items')->where('id',$item->id)->lockForUpdate()->first();
            if (!$locked) $this->fail('Order item is no longer available.');
            $order=DB::table('orders')->where('id',$item->order_id)->lockForUpdate()->first();
            if (!$order || $order->status!=='confirmed') $this->fail('Order status changed.');
            $existing=DB::table('hosting_services')->where('order_item_id',$item->id)->lockForUpdate()->first();
            $now=now();
            if ($existing) {
                if ($existing->status!=='provisioning_retry_ready' || $existing->remote_server_id!==null
                    || (int)$existing->pterodactyl_node_id!==(int)$node->id) $this->fail('Previous service has not been safely reconciled.');
                $latest=DB::table('provisioning_attempts')->where('hosting_service_id',$existing->id)->orderByDesc('id')->lockForUpdate()->first();
                if (!$latest || $latest->status!=='reconciled_absent') $this->fail('Previous attempt has not been reconciled as absent.');
                $serviceId=(int)$existing->id;
                DB::table('hosting_services')->where('id',$serviceId)->update(['status'=>'provisioning','updated_at'=>$now]);
            } else {
                $serviceId=DB::table('hosting_services')->insertGetId([
                    'user_id'=>$item->user_id,'order_item_id'=>$item->id,'hosting_plan_id'=>$item->hosting_plan_id,
                    'pterodactyl_node_id'=>$node->id,'status'=>'provisioning', 'created_at'=>$now,'updated_at'=>$now,
                ]);
            }
            $attemptId=DB::table('provisioning_attempts')->insertGetId([
                'hosting_service_id'=>$serviceId,'action'=>'manual_create','status'=>'pending',
                'idempotency_key'=>(string)Str::uuid(),'created_at'=>$now,'updated_at'=>$now,
            ]);
            return [$serviceId,$attemptId];
        },3);
        $payload=[
            'name'=>mb_substr($item->plan_name.' #'.$item->id,0,80),
            'external_id'=>$externalId,
            'user'=>(int)$link->remote_user_id,
            'egg'=>(int)$item->remote_egg_id,
            'docker_image'=>(string)$egg['attributes']['docker_image'],
            'startup'=>(string)$egg['attributes']['startup'],
            'environment'=>$environment,
            'limits'=>['memory'=>(int)$item->memory_mb,'swap'=>(int)$item->swap_mb,'disk'=>(int)$item->disk_mb,'io'=>500,'cpu'=>(int)$item->cpu_percent],
            'feature_limits'=>['databases'=>(int)$item->databases,'allocations'=>max(0,(int)$item->allocations-1),'backups'=>(int)$item->backups],
            'allocation'=>['default'=>$allocationIds[0],'additional'=>array_slice($allocationIds,1)],
            'start_on_completion'=>false,
        ];
        try {
            $created=$api->createServer($payload);
            DB::transaction(function () use ($serviceId,$attemptId,$created) {
                DB::table('hosting_services')->where('id',$serviceId)->update([
                    'remote_server_id'=>(int)$created['id'],'remote_server_uuid'=>(string)$created['uuid'],
                    'status'=>'provisioned','provisioned_at'=>now(),'updated_at'=>now(),
                ]);
                DB::table('provisioning_attempts')->where('id',$attemptId)->update([
                    'status'=>'succeeded','finished_at'=>now(),'updated_at'=>now(),
                ]);
            },3);
            app(\App\Services\PterohubNotifications::class)->event('service_provisioned',(int)$item->user_id,'#'.$serviceId,'service_provisioned:'.$serviceId);
            return redirect()->route('pterohub.manual.index')->with('ok','Server #'.$created['id'].' created in Pterodactyl and linked to the order item. No payment was collected.');
        } catch (Throwable $e) {
            // The remote server may have been created even when a timeout or DB write fails.
            // Retain service and attempt permanently until explicit reconciliation.
            report($e);
            $definitive = $e instanceof PterohubRemoteHttpException
                && $e->httpStatus >= 400 && $e->httpStatus < 500
                && !in_array($e->httpStatus,[408,409,425,429],true);
            DB::table('hosting_services')->where('id',$serviceId)->whereNull('remote_server_id')->update([
                'status'=>'provisioning_review','last_provision_error'=>'Manual reconciliation required after remote request.','updated_at'=>now(),
            ]);
            DB::table('provisioning_attempts')->where('id',$attemptId)->where('status','pending')->update([
                'status'=>$definitive ? 'rejected' : 'uncertain',
                'error'=>$definitive ? 'Pterodactyl rejected the request with HTTP '.$e->httpStatus.'. Admin reconciliation required.' : 'Remote request outcome requires manual reconciliation.','finished_at'=>now(),'updated_at'=>now(),
            ]);
            return redirect()->route('pterohub.manual.index')->withErrors(['provisioning'=>$definitive
                ? 'Pterodactyl rejected server creation with HTTP '.$e->httpStatus.'. Do NOT retry. Reconcile external ID '.$externalId.' before a new attempt.'
                : 'Creation result is uncertain. Do NOT retry. Check Pterodactyl for external ID '.$externalId.' and reconcile the service manually.']);
        }
    }
}
