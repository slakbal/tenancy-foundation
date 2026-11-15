<?php

namespace Slakbal\TenancyFoundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Slakbal\TenancyFoundation\Enums\TenantResolution;
use Symfony\Component\HttpFoundation\Response;

class BindSessionToHost
{
    public const SESSION_KEY = 'tenancy_foundation.host';

    public function handle(Request $request, Closure $next): Response
    {
        if (TenantResolution::current() !== TenantResolution::DOMAIN || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $boundHost = $session->get(self::SESSION_KEY);

        // A session cookie replayed on another tenant host starts over as a guest there.
        if ($boundHost !== null && $boundHost !== $request->getHost()) {
            $session->invalidate();
            $session->regenerateToken();
        }

        $session->put(self::SESSION_KEY, $request->getHost());

        return $next($request);
    }
}
