<?php

/*
 * Cross-Origin Resource Sharing configuration.
 *
 * The public embed endpoint at /api/spaces/{public_id}/testimonials
 * is hit by third-party sites. It must answer CORS preflight and
 * actual requests with `Access-Control-Allow-Origin: *` (build-order
 * hard rule 8 / PRD §8 / data-model §6).
 *
 * The `paths` key pins the CORS policy to /api/* routes. The
 * controller does NOT set Access-Control-Allow-Origin itself; the
 * header is supplied by Laravel's HandleCors global middleware
 * (driven by this file). The test that asserts the header is the
 * proof that this middleware path is wired.
 *
 * The HandleCors global middleware (Laravel default stack) applies
 * this config to every request — including the embed API route
 * AFTER its `web` middleware group has been removed via
 * `Route::withoutMiddleware('web')`.
 */

return [
    /*
    | Paths the CORS policy applies to. The embed public API lives
    | under /api/*; the rest of the app is same-origin only.
    */
    'paths' => ['api/*'],

    /*
    | Origins allowed. The embed is consumed by anonymous third-party
    | sites; we cannot enumerate them. A literal `*` is correct here.
    */
    'allowed_origins' => ['*'],

    /*
    | Cross-Origin headers allowed on requests.
    */
    'allowed_methods' => ['*'],

    /*
    | Headers the browser is allowed to send on the actual request.
    | `*` covers Content-Type and X-Requested-With.
    */
    'allowed_headers' => ['*'],

    /*
    | Headers the browser is allowed to read on the response.
    */
    'exposed_headers' => [],

    /*
    | Preflight cache lifetime, in seconds. The embed JSON is itself
    | cached for 60s, so a long preflight cache is fine here.
    */
    'max_age' => 86400,

    /*
    | Whether to allow credentials. We don't set cookies on the embed
    | endpoint, so credentials stay disabled.
    */
    'supports_credentials' => false,
];
