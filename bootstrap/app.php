<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register middleware aliases
        $middleware->alias([
            'resolve.tenant' => \App\Http\Middleware\ResolveTenant::class,
            'resolve.tenant.from.path' => \App\Http\Middleware\ResolveTenantFromPath::class,
            'ensure.user.belongs.to.company' => \App\Http\Middleware\EnsureUserBelongsToCompany::class,
            'ensure.branch.access' => \App\Http\Middleware\EnsureBranchAccess::class,
            'ensure.module' => \App\Http\Middleware\EnsureModuleEnabled::class,
            'ensure.subscription' => \App\Http\Middleware\EnsureSubscriptionActive::class,
        ]);
        
        // 🔥 CRITICAL FIX: Add early tenant resolution as global middleware
        // This runs BEFORE auth middleware, ensuring tenant DB is configured
        // when Sanctum tries to look up tokens
        $middleware->api(prepend: [
            \App\Http\Middleware\EarlyTenantResolution::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();