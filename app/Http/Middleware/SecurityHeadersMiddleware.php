<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request and attach standard security headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=()');
        $localHttpScheme = app()->isLocal() ? ' http:' : '';
        $localWsScheme = app()->isLocal() ? ' ws:' : '';

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; "
            ."script-src 'self' 'unsafe-inline' 'unsafe-eval' https:{$localHttpScheme}; "
            ."style-src 'self' 'unsafe-inline' https:{$localHttpScheme}; "
            ."img-src 'self' data: blob: https:{$localHttpScheme}; "
            ."font-src 'self' data: https:{$localHttpScheme}; "
            ."connect-src 'self' wss: https:{$localHttpScheme}{$localWsScheme}; "
            ."frame-ancestors 'self';"
        );

        if ($request->isSecure() || app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
