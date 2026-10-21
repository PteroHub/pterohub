<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: ['webhooks/pterohub/stripe-sandbox','webhooks/pterohub/paypal-sandbox']);
    $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
        return $request->is('client', 'client/*') ? '/client/login' : '/ph-admin/login';
    });
    $middleware->redirectUsersTo(function (\Illuminate\Http\Request $request) {
        return (bool) $request->user()?->is_pterohub_admin ? '/ph-admin' : '/client';
    });
    $middleware->alias([
        'client.only' => \App\Http\Middleware\PterohubClientOnly::class,
    ]);
})
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
