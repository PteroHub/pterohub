<?php
namespace App\Providers;
use App\Support\PterohubMailSettings;
use Illuminate\Support\ServiceProvider;
final class PterohubMailServiceProvider extends ServiceProvider {
 public function boot(): void { PterohubMailSettings::apply(); }
}
