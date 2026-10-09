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
    ->withMiddleware(function (Middleware $middleware) {
        /*
         * Trusted proxies are OFF by default. The switch `CLOUDFLARE_HEADERS_ENABLED`
         * stays off unless the deployment is explicitly behind Cloudflare; flipping
         * it on lets `TrustProxies` accept the `CF-Connecting-IP` header so the rate
         * limiter and audit logs see the real client IP.
         *
         * Turning this on while NOT behind Cloudflare would let any visitor set
         * their own client IP via header — that's why the default is off and
         * the switch lives in .env, not in code.
         */
        if (env('CLOUDFLARE_HEADERS_ENABLED', false)) {
            $middleware->trustProxies(
                at: '*',
                headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
                    | \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();