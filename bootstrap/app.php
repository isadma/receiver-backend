<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'receiver.basic' => \App\Http\Middleware\BasicAuth::class,
        ]);
        $middleware->appendToGroup('web', 'receiver.basic');
        $middleware->appendToGroup('api', 'receiver.basic');

        $middleware->trustProxies(at: '*');

        $hosts = [
            '^receiver\.meredow\.com$',
            '^localhost$',
            '^127\.0\.0\.1$',
        ];

        if (env('APP_ENV', 'production') === 'local') {
            $hosts = [
                ...$hosts,
                '^192\.168\.\d{1,3}\.\d{1,3}$',
                '^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$',
                '^172\.(1[6-9]|2[0-9]|3[0-1])\.\d{1,3}\.\d{1,3}$',
            ];
        }

        $middleware->trustHosts(at: $hosts, subdomains: false);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
