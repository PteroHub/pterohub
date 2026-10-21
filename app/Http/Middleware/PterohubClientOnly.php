<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PterohubClientOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && (bool) $request->user()->is_pterohub_admin) {
            return redirect()->route('pterohub.admin.dashboard');
        }
        return $next($request);
    }
}
