<?php

namespace Slakbal\TenancyFoundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Slakbal\TenancyFoundation\Enums\TenantResolution;
use Symfony\Component\HttpFoundation\Response;

class EnsureKnownTenantHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (TenantResolution::current() === TenantResolution::DOMAIN) {
            $tenantModel = config('tenancy-foundation.models.tenant');

            // Every tenant panel route, login included, answers only on a tenant's own host.
            abort_unless($tenantModel::query()->where(TenantResolution::DOMAIN->attribute(), $request->getHost())->exists(), Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
