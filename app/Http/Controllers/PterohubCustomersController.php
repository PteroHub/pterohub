<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
final class PterohubCustomersController extends Controller {
 private function guard(): void {abort_unless(auth()->check() && (bool) auth()->user()->is_pterohub_admin,403);}
 public function index(Request $request) {
  $this->guard();
  $query=DB::table('users')->leftJoin('pterohub_billing_profiles as b','b.user_id','=','users.id')
    ->select('users.id','users.name','users.email','users.created_at','b.company','b.country');
  $term=trim((string)$request->query('q',''));
  if ($term!=='') {$query->where(function($q)use($term){$q->where('users.name','like','%'.$term.'%')->orWhere('users.email','like','%'.$term.'%');});}
  return view('pterohub.customers.index',['customers'=>$query->orderByDesc('users.id')->paginate(20)->withQueryString(),'term'=>$term]);
 }
 public function show(int $id) {
  $this->guard();$customer=DB::table('users')->where('id',$id)->first();abort_unless($customer,404);
  $profile=DB::table('pterohub_billing_profiles')->where('user_id',$id)->first();
  $counts=['orders'=>DB::table('orders')->where('user_id',$id)->count(),
    'invoices'=>DB::table('invoices')->where('user_id',$id)->count(),
    'services'=>DB::table('hosting_services')->where('user_id',$id)->count(),
    'tickets'=>DB::table('tickets')->where('user_id',$id)->count()];
  $sync=DB::table('pterohub_account_sync')->where('user_id',$id)->first();
  $remoteLink=DB::table('pterohub_pterodactyl_user_links')->where('user_id',$id)->first();
  return view('pterohub.customers.show',compact('customer','profile','counts','sync','remoteLink'));
 }
 public function save(Request $request,int $id) {
  $this->guard();abort_unless(DB::table('users')->where('id',$id)->exists(),404);
  $data=$request->validate([
   'customer_type'=>['required',Rule::in(['individual','business'])],
   'legal_name'=>'nullable|string|max:180','company'=>'nullable|string|max:180',
   'tax_id'=>'nullable|string|max:40','vat_number'=>'nullable|string|max:40',
   'address_line1'=>'nullable|string|max:200','address_line2'=>'nullable|string|max:200',
   'city'=>'nullable|string|max:120','region'=>'nullable|string|max:120',
   'postal_code'=>'nullable|string|max:32','country'=>'nullable|string|size:2|regex:/^[A-Za-z]{2}$/',
   'pec_email'=>'nullable|email|max:200','recipient_code'=>'nullable|string|max:20'
  ]);
  if(isset($data['country']))$data['country']=strtoupper($data['country']);
  $data['updated_at']=now();
  DB::table('pterohub_billing_profiles')->updateOrInsert(['user_id'=>$id],$data+['created_at'=>now()]);
  Log::info('PteroHub billing profile updated',['customer_id'=>$id,'actor_id'=>auth()->id()]);
  return redirect()->route('pterohub.customers.show',$id)->with('ok','Billing profile saved. No invoices or charges were created.');
 }
}
