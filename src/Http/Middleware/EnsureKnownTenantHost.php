<?php

namespace Slakbal\TenancyFoundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Slakbal\TenancyFoundation\Enums\TenantResolution;
use Slakbal\TenancyFoundation\Http\Responses\Contracts\CancelledTenantResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsureKnownTenantHost
{
    // Every tenant panel route, login included, answers only on the host of an existing, active tenant. Always on in
    // domain mode; in subdomain mode only with verify_subdomain_hosts, and never on the central host.
    public function handle(Request $request, Closure $next): Response
    {
        $resolution = TenantResolution::current();

        if ($resolution === TenantResolution::SUBDOMAIN && ! $this->verifiesSubdomainHost($request)) {
            return $next($request);
        }

        $tenant = $resolution->tenantForHost($request->getHost());

        abort_if($tenant === null, Response::HTTP_NOT_FOUND);

        if ($tenant->isCancelled()) {
            $this->dropHostSession($request, $resolution);

            return app(CancelledTenantResponse::class)->toResponse($request, $tenant);
        }

        return $next($request);
    }

    protected function verifiesSubdomainHost(Request $request): bool
    {
        $domain = config('tenancy-foundation.domain');

        return config('tenancy-foundation.verify_subdomain_hosts') && filled($domain) && $request->getHost() !== $domain;
    }

    // Domain mode sessions belong to this host only, so nobody is signed in again on their own when the tenant is
    // reactivated. A subdomain mode session is shared with the user's other tenants and stays.
    protected function dropHostSession(Request $request, TenantResolution $resolution): void
    {
        if ($resolution !== TenantResolution::DOMAIN || ! $request->hasSession()) {
            return;
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
