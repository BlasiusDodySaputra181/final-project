<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureLocalConsole;
use App\Http\Middleware\EnsureOfficer;
use App\Http\Middleware\EnsureStudent;
use App\Http\Middleware\ResolveAdminSession;
use App\Http\Middleware\UseStaticAccessHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.session' => ResolveAdminSession::class,
            'admin' => EnsureAdmin::class,
            'officer' => EnsureOfficer::class,
            'student' => EnsureStudent::class,
            'local.console' => EnsureLocalConsole::class,
        ]);

        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $middleware->append(UseStaticAccessHost::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
