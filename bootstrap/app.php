<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\VerifyUserRole;
use App\Http\Middleware\EnsureUserIsApproved;
use App\Http\Middleware\VerifyManaStoreApiKey;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: [
            'stripe/*',
        ]);
        $middleware->alias([
            'user.approved' => EnsureUserIsApproved::class,
            'role' => VerifyUserRole::class,
            'manastore.auth' => VerifyManaStoreApiKey::class,
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
