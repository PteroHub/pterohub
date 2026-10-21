<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
final class PterohubBrandController extends Controller {
 private function authorizeAdmin(): void { abort_unless(auth()->check() && (bool) auth()->user()->is_pterohub_admin,403); }
 public function edit() {
  $this->authorizeAdmin();
  $record=Schema::hasTable('pterohub_brand_settings')?DB::table('pterohub_brand_settings')->where('id',1)->first():null;
  return view('pterohub.settings.brand',[ 'brand'=>$record,'fallbackName'=>env('APP_NAME','PteroHub'),'fallbackSender'=>config('mail.from.name'), 'fromAddress'=>config('mail.from.address') ]);
 }
 public function update(Request $request) {
  $this->authorizeAdmin();
  $validated=$request->validate(['company_name'=>['nullable','string','max:120'], 'sender_name'=>['nullable','string','max:120'], 'reply_to_email'=>['nullable','email','max:254']]);
  abort_unless(Schema::hasTable('pterohub_brand_settings'),503,'Run migrations first');
  $values=[
    'company_name'=>trim($validated['company_name']??'') ?: null,
    'sender_name'=>trim($validated['sender_name']??'') ?: null,
    'reply_to_email'=>trim($validated['reply_to_email']??'') ?: null,
    'updated_at'=>now(),
  ];
  DB::transaction(function() use ($values) {
    if (DB::table('pterohub_brand_settings')->where('id',1)->exists()) {
      DB::table('pterohub_brand_settings')->where('id',1)->update($values);
    } else {
      DB::table('pterohub_brand_settings')->insert(['id'=>1,'created_at'=>now()]+$values);
    }
  });
  return redirect()->route('pterohub.brand.edit')->with('ok','Brand settings saved.');
 }
}
