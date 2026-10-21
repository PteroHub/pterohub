<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
final class PterohubBrandServiceProvider extends ServiceProvider {
 public function boot(): void {
  try {
   if (!Schema::hasTable('pterohub_brand_settings')) return;
   $brand=DB::table('pterohub_brand_settings')->where('id',1)->first();
   if (!$brand) return;
   $company=trim((string)($brand->company_name??''));
   $sender=trim((string)($brand->sender_name??''));
   if ($company!=='') config(['app.name'=>$company]);
   if ($sender!=='' || $company!=='') config(['mail.from.name'=>$sender!==''?$sender:$company]);
  } catch (Throwable $e) { /* Brand settings must never take the application offline. */ }
 }
}
