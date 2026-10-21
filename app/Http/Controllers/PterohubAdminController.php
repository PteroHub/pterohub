<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class PterohubAdminController extends Controller {
    private function onlyAdmin(): void { abort_unless(Auth::check() && (bool) Auth::user()->is_pterohub_admin, 403); }
    public function loginForm() { return view('pterohub.login'); }
    public function login(Request $request) {
        $data=$request->validate(['email'=>'required|email','password'=>'required|string']);
        if (!Auth::attempt($data)) return back()->withErrors(['email'=>'Invalid credentials.'])->onlyInput('email');
        $request->session()->regenerate();
        if (!(bool) Auth::user()->is_pterohub_admin) { Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); abort(403); }
        return redirect()->route('pterohub.admin.dashboard');
    }
    public function logout(Request $request) { Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('pterohub.login'); }
    public function dashboard() {
        $this->onlyAdmin();
        return view('pterohub.dashboard',[ 'eggs'=>DB::table('pterodactyl_eggs')->leftJoin('games','games.id','=','pterodactyl_eggs.game_id')->select('pterodactyl_eggs.*','games.name as game_name')->orderBy('pterodactyl_eggs.remote_id')->get(),
            'games'=>DB::table('games')->orderBy('name')->get(), 'plans'=>DB::table('hosting_plans')->join('games','games.id','=','hosting_plans.game_id')->join('pterodactyl_eggs','pterodactyl_eggs.id','=','hosting_plans.pterodactyl_egg_id')->select('hosting_plans.*','games.name as game_name','pterodactyl_eggs.name as egg_name')->orderByDesc('hosting_plans.id')->get(),
            'prices'=>DB::table('plan_prices')->orderBy('billing_months')->get(), 'nodeCount'=>DB::table('pterodactyl_nodes')->count()]);
    }
    public function game(Request $request) {
        $this->onlyAdmin(); $data=$request->validate(['name'=>'required|string|max:150','engine'=>'nullable|string|max:120','description'=>'nullable|string|max:1000']);
        $slug=Str::slug($data['name']); if($slug==='') return back()->withErrors(['name'=>'Invalid name']);
        DB::table('games')->updateOrInsert(['slug'=>$slug],['name'=>$data['name'],'engine'=>$data['engine']??null,'description'=>$data['description']??null,'updated_at'=>now()] + (DB::table('games')->where('slug',$slug)->exists()?[]:['active'=>false,'created_at'=>now()]));
        return back()->with('ok','Game saved as inactive.');
    }
    public function assignEgg(Request $request, int $egg) {
        $this->onlyAdmin(); $data=$request->validate(['game_id'=>'nullable|integer|exists:games,id']);
        DB::table('pterodactyl_eggs')->where('id',$egg)->update(['game_id'=>$data['game_id']??null,'updated_at'=>now()]);
        return back()->with('ok','Egg association saved.');
    }
    public function plan(Request $request) {
        $this->onlyAdmin();
        $data=$request->validate(['name'=>'required|string|max:150','game_id'=>'required|integer|exists:games,id',
            'pterodactyl_egg_id'=>'required|integer|exists:pterodactyl_eggs,id','memory_mb'=>'required|integer|min:128|max:1048576',
            'disk_mb'=>'required|integer|min:512|max:10485760','cpu_percent'=>'required|integer|min:1|max:1600',
            'allocations'=>'required|integer|min:1|max:20','backups'=>'required|integer|min:0|max:100',
            'databases'=>'required|integer|min:0|max:100','billing_months'=>'required|integer|in:1,3,6,12',
            'amount_minor'=>'required|integer|min:0|max:100000000']);
        if (!DB::table('pterodactyl_eggs')->where('id',$data['pterodactyl_egg_id'])->where('game_id',$data['game_id'])->exists())
            return back()->withErrors(['pterodactyl_egg_id'=>'Assign this Egg to the selected game first.']);
        $slug=Str::slug($data['name']).'-'.Str::lower(Str::random(6));
        DB::transaction(function()use($data,$slug){
            $id=DB::table('hosting_plans')->insertGetId(['name'=>$data['name'],'slug'=>$slug,'game_id'=>$data['game_id'],
                'pterodactyl_egg_id'=>$data['pterodactyl_egg_id'],'memory_mb'=>$data['memory_mb'],'disk_mb'=>$data['disk_mb'],
                'cpu_percent'=>$data['cpu_percent'],'allocations'=>$data['allocations'],'backups'=>$data['backups'],
                'databases'=>$data['databases'],'active'=>false,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('plan_prices')->insert(['hosting_plan_id'=>$id,'currency'=>'EUR','billing_months'=>$data['billing_months'],
                'amount_minor'=>$data['amount_minor'],'setup_minor'=>0,'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('ok','Plan created. Inactive by default.');
    }
}
