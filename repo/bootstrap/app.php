<?php

use App\Http\Middleware\AuthenticateToken;
use App\Http\Middleware\AuthorizePermission;
use App\Http\Middleware\AuthorizeRole;
use App\Http\Middleware\CheckLockout;
use App\Http\Middleware\LogRequestResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            LogRequestResponse::class,
        ]);

        $middleware->alias([
            'auth.token' => AuthenticateToken::class,
            'role' => AuthorizeRole::class,
            'permission' => AuthorizePermission::class,
            'check.lockout' => CheckLockout::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
