<?php

namespace Slakbal\TenancyFoundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (array_filter([...self::HEADERS, ...config('tenancy-foundation.response_headers')], filled(...)) as $name => $value) {
            $response->headers->set($name, $value);
        }

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
